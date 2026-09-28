<?php

namespace Lunar\DiscountTypes;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Lunar\Base\Purchasable;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdownLine;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class BuyXGetY extends AbstractDiscountType
{
    /**
     * Return the name of the discount.
     */
    public function getName(): string
    {
        return __('lunarpanel::discount.form.buy_x_get_y.heading');
    }

    /**
     * Return the reward quantity for the discount
     *
     * @param  int  $linesQuantity
     * @param  int  $minQty
     * @param  int  $rewardQty
     * @param  int  $maxRewardQty
     * @return int
     */
    public function getRewardQuantity($linesQuantity, $minQty, $rewardQty, $maxRewardQty = null)
    {
        if ($linesQuantity < $minQty) {
            return 0;
        }

        $result = floor(($linesQuantity / ($minQty ?: 1)) * $rewardQty);

        return $maxRewardQty ? min($result, $maxRewardQty) : $result;
    }

    /**
     * Called just before cart totals are calculated.
     *
     * @return CartLine
     */
    public function apply(CartContract $cart): CartContract
    {
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        $data = $this->discount->data;

        $minQty = $data['min_qty'] ?? null;
        $rewardQty = $data['reward_qty'] ?? 1;
        $maxRewardQty = $data['max_reward_qty'] ?? null;
        $automaticallyAddRewards = $data['automatically_add_rewards'] ?? false;

        $hasCollectionDiscountables = $this->discount->discountableConditions
            ->where('discountable_type', LunarCollection::morphName())
            ->isNotEmpty()
            || $this->discount->discountableRewards
                ->where('discountable_type', LunarCollection::morphName())
                ->isNotEmpty();

        $productCollectionIds = $hasCollectionDiscountables
            ? $this->productCollectionIdsForCart($cart)
            : collect();

        // Get all discountables that are eligible. Gift lines never count toward
        // condition quantity so dual-use (paid + free same SKU) cannot inflate min_qty.
        $conditions = $cart->lines->reject(
            fn ($line) => $this->lineHasAddedByDiscount($line)
                || ! $this->discount->discountableConditions->contains(
                    fn ($item) => $this->lineMatchesDiscountable($item, $line, $productCollectionIds)
                )
        );

        $totalQuantity = $conditions->sum('quantity');

        // Below min_qty (or no qualifying parents): strip this discount's gift lines.
        if (! $conditions->count() || ($minQty && $totalQuantity < $minQty)) {
            $this->trimGiftLinesToBudget($cart, 0);

            return $cart;
        }

        // How many products are rewarded?
        $totalRewardQty = $this->getRewardQuantity(
            $totalQuantity,
            $minQty,
            $rewardQty,
            $maxRewardQty
        );

        // When shopper selections (or auto-add) drive gift lines, only discount those
        // gift lines — never a shopper-paid dual-use line for the same purchasable.
        $restrictToGiftLines = $automaticallyAddRewards || $this->cartHasExplicitGiftSelectionMeta($cart);

        if (! $totalRewardQty) {
            $this->trimGiftLinesToBudget($cart, 0);

            return $cart;
        }

        $remainingRewardQty = $totalRewardQty;
        $affectedLines = collect();
        $discountTotal = 0;

        // Get the reward lines and sort by cheapest first.
        $cart->lines
            ->filter(
                fn ($line) => (! $restrictToGiftLines || $this->lineIsGiftForDiscount($line))
                    && $this->discount->discountableRewards->contains(
                        fn ($item) => $this->lineMatchesDiscountable($item, $line, $productCollectionIds)
                    )
            )
            ->sortBy('unitPrice.value')
            ->each(function ($rewardLine) use (
                $cart,
                $conditions,
                $minQty,
                $rewardQty,
                &$remainingRewardQty,
                &$discountTotal,
                $affectedLines,
            ) {
                if (! $remainingRewardQty) {
                    return false;
                }

                $qtyToAllocate = $this->rewardQtyToAllocate($rewardLine, $remainingRewardQty);

                if (! $qtyToAllocate) {
                    return;
                }

                $affectedLines->push(new DiscountBreakdownLine(
                    line: $rewardLine,
                    quantity: $qtyToAllocate
                ));

                $conditionQtyToAllocate = $qtyToAllocate * ($minQty - $rewardQty);

                $conditions->each(function ($conditionLine) use ($affectedLines, &$conditionQtyToAllocate) {
                    if (! $conditionQtyToAllocate) {
                        return false;
                    }

                    $qtyCanBeApplied = min(
                        $conditionQtyToAllocate,
                        $conditionLine->quantity - ($affectedLines->firstWhere('line', $conditionLine)?->quantity ?? 0)
                    );

                    if ($qtyCanBeApplied < 1) {
                        return;
                    }

                    $conditionQtyToAllocate -= $qtyCanBeApplied;

                    $affectedLines->push(new DiscountBreakdownLine(
                        line: $conditionLine,
                        quantity: $qtyCanBeApplied
                    ));
                });

                $remainingRewardQty -= $qtyToAllocate;

                $lineDiscountTotal = $rewardLine->unitPrice->value * $qtyToAllocate;
                $discountTotal += $lineDiscountTotal;

                $this->applyFreeGiftLinePricing(
                    $rewardLine,
                    $cart,
                    $lineDiscountTotal,
                    max(0, $rewardLine->subTotal->value - $lineDiscountTotal),
                );

                $cart->freeItems = ($cart->freeItems ?? collect())->push($rewardLine->purchasable);
            });

        // Auto-add OR shopper picker selections (selected_gift_rewards meta): create/trim gift lines.
        // automatically_add_rewards=false still fulfills explicit modal selections — it only skips random picks.
        [$affectedLines, $discountTotal] = $this->fulfillConfiguredGiftLines(
            $cart,
            $restrictToGiftLines,
            (int) $totalRewardQty,
            $affectedLines,
            $discountTotal,
            $automaticallyAddRewards,
        );

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($discountTotal, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        $cart->discounts->push($this);

        return $cart;
    }

    /**
     * Trim and auto-add/selection-fulfill gift lines when configured.
     *
     * @return array{0: Collection, 1: int}
     */
    protected function fulfillConfiguredGiftLines(
        CartContract $cart,
        bool $restrictToGiftLines,
        int $totalRewardQty,
        Collection $affectedLines,
        int $discountTotal,
        bool $automaticallyAddRewards,
    ): array {
        if (! $restrictToGiftLines) {
            return [$affectedLines, $discountTotal];
        }

        // The first loop only applies discount totals to existing gift lines; it does
        // not keep line quantities inside max_reward_qty. Reconcile the budget before
        // auto-adding so incremental cart updates cannot grow gifts past the cap.
        $this->trimGiftLinesToBudget($cart, $totalRewardQty);

        [$affectedLines, $discountTotal] = $this->processAutomaticRewards(
            $cart,
            max(0, $totalRewardQty - $this->giftUnitsForDiscount($cart)),
            $affectedLines,
            $discountTotal,
            allowAutoPick: $automaticallyAddRewards,
        );

        // Final safety net: never leave more gift units in the cart than max_reward_qty.
        $this->trimGiftLinesToBudget($cart, $totalRewardQty);

        return [$affectedLines, $discountTotal];
    }

    /**
     * Units of a reward line to discount given remaining reward budget.
     */
    protected function rewardQtyToAllocate(CartLine $rewardLine, int $remainingRewardQty): int
    {
        $remainder = (int) floor($remainingRewardQty);
        $qtyToAllocate = $remainder;

        if ($rewardLine->quantity < $remainder) {
            $remainder = $rewardLine->quantity % $remainingRewardQty;
            $qtyToAllocate = (int) round(($remainingRewardQty - $remainder) / $rewardLine->quantity);
        }

        return ($rewardLine->quantity == 1 && $remainder) ? 1 : $qtyToAllocate;
    }

    /**
     * Map product id → collection ids for collection-based discountables.
     *
     * @return Collection<int|string, Collection<int, int|string>>
     */
    protected function productCollectionIdsForCart(CartContract $cart): Collection
    {
        $products = $cart->lines->map(fn ($line) => $line->purchasable->product)->unique('id');
        $products->loadMissing('collections');

        return $products->mapWithKeys(fn ($p) => [$p->id => $p->collections->pluck('id')]);
    }

    /**
     * @return array{0: Collection, 1: int}
     */
    protected function processAutomaticRewards(
        CartContract $cart,
        int $remainingRewardQty,
        Collection $affectedLines,
        int $discountTotal,
        bool $allowAutoPick = true,
    ): array {
        // Reward lines this run has added, keyed by purchasable. The check below
        // reads $cart->lines, which never receives a line made here, so without
        // this a reward quantity of three opens three lines of one rather than
        // one line of three.
        $addedRewardLines = [];

        if ($remainingRewardQty < 1) {
            return [$affectedLines, $discountTotal];
        }

        // Fulfillable products per collection reward, hydrated once here rather
        // than re-queried on every iteration of the allocation loop below.
        $fulfillableCollectionProducts = [];

        $fulfillableRewards = $this->discount->discountableRewards->filter(
            function ($discountableReward) use (&$fulfillableCollectionProducts) {
                $rewardItem = $discountableReward->discountable;

                return match (true) {
                    ! $rewardItem => false,
                    $rewardItem instanceof LunarCollection => tap(
                        $rewardItem->products()
                            ->with('variants')
                            ->get()
                            ->filter(fn ($p) => $p->variants->first()?->canBeFulfilledAtQuantity(1))
                            ->values(),
                        fn ($products) => $fulfillableCollectionProducts[$rewardItem->id] = $products,
                    )->isNotEmpty(),
                    $rewardItem instanceof Purchasable => $rewardItem->canBeFulfilledAtQuantity(1),
                    default => (bool) $rewardItem->variants->first()?->canBeFulfilledAtQuantity(1),
                };
            }
        );

        if ($fulfillableRewards->isEmpty()) {
            return [$affectedLines, $discountTotal];
        }

        $productVariantRewards = $fulfillableRewards->filter(
            fn ($discountableReward) => ! ($discountableReward->discountable instanceof LunarCollection)
        );

        $selectedPurchasables = $this->resolveSelectedGiftPurchasables($cart, $remainingRewardQty);

        if ($selectedPurchasables->isNotEmpty()) {
            $selectedPurchasables->each(function (array $selection) use (
                $cart,
                &$remainingRewardQty,
                &$affectedLines,
                &$discountTotal,
                &$addedRewardLines,
            ) {
                if ($remainingRewardQty <= 0) {
                    return false;
                }

                /** @var Purchasable $purchasable */
                $purchasable = $selection['purchasable'];

                Collection::times((int) $selection['quantity'])->each(function () use (
                    $cart,
                    $purchasable,
                    $selection,
                    &$remainingRewardQty,
                    &$affectedLines,
                    &$discountTotal,
                    &$addedRewardLines,
                ) {
                    if ($remainingRewardQty <= 0) {
                        return false;
                    }

                    [$affectedLines, $discountTotal, $remainingRewardQty, $addedRewardLines] = $this->allocateAutomaticRewardUnit(
                        cart: $cart,
                        purchasable: $purchasable,
                        selectedRewardItem: $selection['reward_item'],
                        remainingRewardQty: $remainingRewardQty,
                        affectedLines: $affectedLines,
                        discountTotal: $discountTotal,
                        addedRewardLines: $addedRewardLines,
                    );
                });
            });
        } elseif (
            ($this->cartHasExplicitGiftSelectionMeta($cart) && ! $this->cartHasPositiveGiftSelectionDemand($cart))
            || ! $allowAutoPick
        ) {
            // Explicit skip, or picker-only with no unmet selections — do not auto-add.
            return [$affectedLines, $discountTotal];
        }

        // Auto-add single reward: top up remaining budget (including after qty
        // increase when selected_gift_rewards meta still lists only 1 unit).
        // Multi-reward never random-picks — storefront modal is the grant path.
        if ($remainingRewardQty < 1 || ! $allowAutoPick || $productVariantRewards->count() > 1) {
            return [$affectedLines, $discountTotal];
        }

        while ($remainingRewardQty > 0) {
            [$purchasable, $selectedRewardItem] = $this->resolvePurchasableFromRewardItem(
                $fulfillableRewards->random()->discountable,
                $fulfillableCollectionProducts,
            );

            if (! $purchasable) {
                $remainingRewardQty--;

                continue;
            }

            [$affectedLines, $discountTotal, $remainingRewardQty, $addedRewardLines] = $this->allocateAutomaticRewardUnit(
                cart: $cart,
                purchasable: $purchasable,
                selectedRewardItem: $selectedRewardItem,
                remainingRewardQty: $remainingRewardQty,
                affectedLines: $affectedLines,
                discountTotal: $discountTotal,
                addedRewardLines: $addedRewardLines,
            );
        }

        return [$affectedLines, $discountTotal];
    }

    /**
     * Resolve a purchasable (and display reward item) from a discountable reward.
     *
     * @param  array<int|string, Collection>  $fulfillableCollectionProducts
     * @return array{0: ?Purchasable, 1: mixed}
     */
    protected function resolvePurchasableFromRewardItem(
        mixed $selectedRewardItem,
        array $fulfillableCollectionProducts,
    ): array {
        if ($selectedRewardItem instanceof LunarCollection) {
            $product = $fulfillableCollectionProducts[$selectedRewardItem->id]->random();

            return [$product->variants->first(), $product];
        }

        if ($selectedRewardItem instanceof Purchasable) {
            return [$selectedRewardItem, $selectedRewardItem];
        }

        return [$selectedRewardItem->variants->first(), $selectedRewardItem];
    }

    /**
     * Resolve shopper gift selections from condition cart line meta.
     *
     * Returns only *unmet* units (wanted across all parents minus gift lines
     * already tagged for this discount), capped by the remaining reward budget.
     * Otherwise topping up a new parent re-spends budget on earlier lines'
     * selections and the new (or qty-2) parent's second gift never appears.
     *
     * @return Collection<int, array{purchasable: Purchasable, reward_item: mixed, quantity: int}>
     */
    protected function resolveSelectedGiftPurchasables(CartContract $cart, int $remainingRewardQty): Collection
    {
        $wantedByKey = $cart->lines
            ->flatMap(fn (CartLine $line) => $this->selectedGiftRewardRowsFromLine($line))
            ->groupBy('key')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'purchasable' => $first['purchasable'],
                    'reward_item' => $first['reward_item'],
                    'quantity' => (int) $group->sum('quantity'),
                ];
            });

        if ($wantedByKey->isEmpty() || $remainingRewardQty < 1) {
            return collect();
        }

        $fulfilledByKey = $cart->lines
            ->filter(fn (CartLine $line) => $this->lineIsGiftForDiscount($line))
            ->filter(fn (CartLine $line) => $line->purchasable instanceof Purchasable)
            ->groupBy(fn (CartLine $line) => $line->purchasable->getMorphClass().':'.$line->purchasable->id)
            ->map(fn (Collection $lines) => (int) $lines->sum('quantity'));

        $allocated = 0;

        return $wantedByKey
            ->map(function (array $selection, string $key) use ($fulfilledByKey) {
                $selection['quantity'] = max(
                    0,
                    (int) $selection['quantity'] - (int) ($fulfilledByKey[$key] ?? 0)
                );

                return $selection;
            })
            ->filter(fn (array $selection) => $selection['quantity'] > 0)
            ->values()
            ->map(function (array $selection) use (&$allocated, $remainingRewardQty) {
                $allowed = min($selection['quantity'], max(0, $remainingRewardQty - $allocated));
                $selection['quantity'] = $allowed;
                $allocated += $allowed;

                return $selection;
            })
            ->filter(fn (array $selection) => $selection['quantity'] > 0)
            ->values();
    }

    /**
     * Parse selected_gift_rewards rows from a cart line into purchasable selections.
     *
     * @return Collection<int, array{key: string, purchasable: Purchasable, reward_item: mixed, quantity: int}>
     */
    protected function selectedGiftRewardRowsFromLine(CartLine $line): Collection
    {
        $raw = $this->normalizeToArray($this->metaValue($line->meta ?? null, 'selected_gift_rewards'));

        if ($raw === null) {
            return collect();
        }

        return collect($raw)
            ->map(fn (mixed $row) => $this->normalizeToArray($row))
            ->filter()
            ->map(function (array $row) {
                $quantity = max(0, (int) ($row['quantity'] ?? 0));
                $resolved = $quantity < 1
                    ? null
                    : $this->resolveSelectionPurchasable(
                        (int) ($row['variant_id'] ?? 0),
                        (int) ($row['product_id'] ?? 0),
                    );

                return $resolved === null ? null : [
                    'key' => $resolved['purchasable']->getMorphClass().':'.$resolved['purchasable']->id,
                    'purchasable' => $resolved['purchasable'],
                    'reward_item' => $resolved['reward_item'],
                    'quantity' => $quantity,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Resolve a shopper selection row to a purchasable and reward display item.
     *
     * @return array{purchasable: Purchasable, reward_item: mixed}|null
     */
    protected function resolveSelectionPurchasable(int $variantId, int $productId): ?array
    {
        if ($variantId > 0) {
            $purchasable = ProductVariant::query()->with('product')->find($variantId);

            return $purchasable instanceof Purchasable
                ? ['purchasable' => $purchasable, 'reward_item' => $purchasable->product ?? $purchasable]
                : null;
        }

        if ($productId < 1) {
            return null;
        }

        $product = Product::query()->with('variants')->find($productId);
        $purchasable = $product?->variants->first();

        return $purchasable instanceof Purchasable
            ? ['purchasable' => $purchasable, 'reward_item' => $product]
            : null;
    }

    /**
     * Allocate a single automatic reward unit onto the cart.
     *
     * Always targets a gift line for this discount (existing or newly created).
     * Never merges free units into a shopper-paid line for the same purchasable.
     *
     * @param  array<string, CartLine>  $addedRewardLines
     * @return array{0: Collection, 1: int, 2: int, 3: array<string, CartLine>}
     */
    protected function allocateAutomaticRewardUnit(
        CartContract $cart,
        Purchasable $purchasable,
        mixed $selectedRewardItem,
        int $remainingRewardQty,
        Collection $affectedLines,
        int $discountTotal,
        array $addedRewardLines,
    ): array {
        $rewardKey = $purchasable->getMorphClass().':'.$purchasable->id;
        $rewardLine = $addedRewardLines[$rewardKey] ?? $this->findGiftLineForPurchasable($cart, $purchasable);
        $allocated = $rewardLine->quantity ?? 0;

        if (! $purchasable->canBeFulfilledAtQuantity($allocated + 1)) {
            return [$affectedLines, $discountTotal, $remainingRewardQty - 1, $addedRewardLines];
        }

        $rewardLine = $rewardLine
            ? $this->incrementExistingGiftLine($rewardLine, $cart, $purchasable)
            : $this->createGiftLine($cart, $purchasable, $selectedRewardItem);

        $addedRewardLines[$rewardKey] = $rewardLine;

        $meta = $this->lineMetaAsObject($rewardLine);
        $added = (array) ($meta->added_by_discount ?? []);
        $discountId = $this->discount->id;
        $added[$discountId] = ($added[$discountId] ?? 0) + 1;
        $meta->added_by_discount = $added;

        $affectedLine = $affectedLines->first(fn ($line) => $line->line == $rewardLine);
        $affectedLine
            ? $affectedLine->quantity++
            : $affectedLines->push(new DiscountBreakdownLine(line: $rewardLine, quantity: 1));

        $unitPrice = $rewardLine->unitPrice->value;
        $lineDiscountTotal = $unitPrice * $rewardLine->quantity;
        $discountTotal += $unitPrice;

        $this->applyFreeGiftLinePricing(
            $rewardLine,
            $cart,
            $lineDiscountTotal,
            max(0, $rewardLine->subTotal->value - $lineDiscountTotal),
        );

        $rewardLine->meta = $meta;
        $rewardLine->save();

        when(
            ! $cart->lines->contains(fn ($line) => $line->is($rewardLine)),
            fn () => $cart->lines->push($rewardLine)
        );

        return [$affectedLines, $discountTotal, $remainingRewardQty - 1, $addedRewardLines];
    }

    /**
     * Increment quantity on an existing gift line and refresh totals.
     */
    protected function incrementExistingGiftLine(
        CartLine $rewardLine,
        CartContract $cart,
        Purchasable $purchasable,
    ): CartLine {
        $rewardLine->quantity++;

        $lineTotal = $rewardLine->unitPrice->value * $rewardLine->quantity;
        $unitQuantity = $purchasable->getUnitQuantity();

        $rewardLine->subTotal = new Price($lineTotal, $cart->currency, $unitQuantity);
        $rewardLine->total = new Price($lineTotal, $cart->currency, $unitQuantity);

        return $rewardLine;
    }

    /**
     * Create a new gift cart line for the purchasable.
     */
    protected function createGiftLine(
        CartContract $cart,
        Purchasable $purchasable,
        mixed $selectedRewardItem,
    ): CartLine {
        $rewardLine = $cart->lines()->make([
            'purchasable_type' => $purchasable->getMorphClass(),
            'purchasable_id' => $purchasable->id,
            'quantity' => 1,
        ]);

        $cart->freeItems ??= collect();

        when(
            $selectedRewardItem && ! $cart->freeItems->contains($selectedRewardItem),
            fn () => $cart->freeItems->push($selectedRewardItem)
        );

        $rewardLine = app(Pipeline::class)
            ->send($rewardLine)
            ->through(config('lunar.cart.pipelines.cart_lines', []))
            ->thenReturn(function ($cartLine) {
                $cartLine->cacheProperties();

                return $cartLine;
            });

        $unitQuantity = $purchasable->getUnitQuantity();

        $rewardLine->subTotal = new Price($rewardLine->unitPrice->value, $cart->currency, $unitQuantity);
        $rewardLine->taxAmount = new Price(0, $cart->currency, $unitQuantity);
        $rewardLine->total = new Price($rewardLine->unitPrice->value, $cart->currency, $unitQuantity);

        return $rewardLine;
    }

    /**
     * Zero (or discount) a free gift line for both Lunar totals and storefront WithoutCoupon fields.
     *
     * CalculateLines seeds subTotalDiscountedWithoutCoupon* from the full line price before
     * ApplyDiscounts. AdvancedAmountOff updates those fields; BXGY must too or cart Subtotal
     * (subTotalDiscountedWithoutCouponIncTax) still includes the gift at full price.
     */
    protected function applyFreeGiftLinePricing(
        CartLine $rewardLine,
        CartContract $cart,
        int $lineDiscountTotal,
        int $subTotalDiscounted,
    ): void {
        $rewardLine->discountTotal = new Price(
            $lineDiscountTotal,
            $cart->currency,
            1
        );

        $rewardLine->discountTotalWithoutCoupon = new Price(
            $lineDiscountTotal,
            $cart->currency,
            1
        );

        $rewardLine->subTotalDiscounted = new Price(
            $subTotalDiscounted,
            $cart->currency,
            1
        );

        $rewardLine->subTotalDiscountedWithoutCoupon = new Price(
            $subTotalDiscounted,
            $cart->currency,
            1
        );

        $rewardLine->subTotalDiscountedWithoutCouponIncTax = $this->convertGiftPriceToIncTax(
            $rewardLine,
            $rewardLine->subTotalDiscountedWithoutCoupon,
        );
    }

    /**
     * Convert an ex-tax price to include tax for storefront display fields.
     */
    protected function convertGiftPriceToIncTax(CartLine $line, Price $price): Price
    {
        if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
            return $price;
        }

        $taxRate = $line->purchasable?->getTaxRate() ?? 0.0;

        return new Price(
            (int) round($price->value * (1 + $taxRate)),
            $price->currency,
            $price->unitQty
        );
    }

    /**
     * Sum gift line quantities tagged for this discount.
     */
    protected function giftUnitsForDiscount(CartContract $cart): int
    {
        return (int) $cart->lines
            ->filter(fn (CartLine $line) => $this->lineIsGiftForDiscount($line))
            ->sum(fn (CartLine $line) => (int) $line->quantity);
    }

    /**
     * Reduce (or remove) gift lines so total gift units do not exceed the reward budget.
     */
    protected function trimGiftLinesToBudget(CartContract $cart, int $totalRewardQty): void
    {
        $giftLines = $cart->lines
            ->filter(fn (CartLine $line) => $this->lineIsGiftForDiscount($line))
            ->sortByDesc(fn (CartLine $line) => (int) $line->quantity)
            ->values();

        $overflow = max(0, (int) $giftLines->sum(fn (CartLine $line) => (int) $line->quantity) - $totalRewardQty);

        if ($overflow < 1) {
            return;
        }

        $giftLines->each(function (CartLine $line) use (&$overflow, $cart) {
            if ($overflow < 1) {
                return false;
            }

            $quantity = (int) $line->quantity;
            $reduceBy = min($quantity, $overflow);
            $overflow -= $reduceBy;
            $newQuantity = $quantity - $reduceBy;

            if ($newQuantity < 1) {
                $lineId = (int) $line->id;
                $line->delete();
                // Use setRelation — `$cart->lines = …` writes a non-column attribute and
                // breaks later `$cart->save()` (e.g. gift slot meta persistence).
                $cart->setRelation(
                    'lines',
                    $cart->lines
                        ->reject(fn (CartLine $cartLine) => (int) $cartLine->id === $lineId)
                        ->values()
                );

                return;
            }

            $line->quantity = $newQuantity;

            $meta = $this->lineMetaAsObject($line);
            $added = (array) ($meta->added_by_discount ?? []);

            when(isset($added[$this->discount->id]), function () use ($added, $meta, $line, $reduceBy) {
                $added[$this->discount->id] = max(1, (int) $added[$this->discount->id] - $reduceBy);
                $meta->added_by_discount = $added;
                $line->meta = $meta;
            });

            $line->save();
        });
    }

    /**
     * Whether any cart line carries an explicit selected_gift_rewards key (including empty skip).
     */
    protected function cartHasExplicitGiftSelectionMeta(CartContract $cart): bool
    {
        return $cart->lines->contains(
            fn (CartLine $line) => $this->metaHasKey($line->meta ?? null, 'selected_gift_rewards')
        );
    }

    /**
     * Whether any condition line asks for at least one selected gift unit.
     *
     * Distinguishes an explicit skip (`selected_gift_rewards` => []) from a
     * positive pick that may already be fulfilled (qty increase still tops up).
     */
    protected function cartHasPositiveGiftSelectionDemand(CartContract $cart): bool
    {
        return $cart->lines->contains(function (CartLine $line) {
            return collect(
                $this->normalizeToArray($this->metaValue($line->meta ?? null, 'selected_gift_rewards')) ?? []
            )->contains(function (mixed $row) {
                $row = $this->normalizeToArray($row);

                return $row !== null && (int) ($row['quantity'] ?? 0) > 0;
            });
        });
    }

    /**
     * Whether the cart line was auto-added as a gift (any discount).
     */
    protected function lineHasAddedByDiscount(CartLine $line): bool
    {
        $added = $this->normalizeToArray($this->metaValue($line->meta ?? null, 'added_by_discount'));

        return $added !== null && $added !== [];
    }

    /**
     * Whether the cart line is a gift line for this discount.
     */
    protected function lineIsGiftForDiscount(CartLine $line): bool
    {
        $added = $this->normalizeToArray($this->metaValue($line->meta ?? null, 'added_by_discount'));

        return $added !== null && (
            array_key_exists($this->discount->id, $added)
            || array_key_exists((string) $this->discount->id, $added)
        );
    }

    /**
     * Find an existing gift line for this discount and purchasable.
     */
    protected function findGiftLineForPurchasable(CartContract $cart, Purchasable $purchasable): ?CartLine
    {
        return $cart->lines->first(function ($line) use ($purchasable) {
            return $line->purchasable->id == $purchasable->id
                && $line->purchasable->getMorphClass() === $purchasable->getMorphClass()
                && $this->lineIsGiftForDiscount($line);
        });
    }

    /**
     * Whether a cart line matches a discountable condition or reward row.
     */
    protected function lineMatchesDiscountable(mixed $item, CartLine $line, Collection $productCollectionIds): bool
    {
        return match (true) {
            $item->discountable_type == Product::morphName()
                && $item->discountable_id == $line->purchasable->product->id => true,
            $item->discountable_type == ProductVariant::morphName()
                && $item->discountable_id == $line->purchasable->id => true,
            $item->discountable_type == LunarCollection::morphName()
                && ($productCollectionIds->get($line->purchasable->product->id) ?? collect())
                    ->contains($item->discountable_id) => true,
            default => false,
        };
    }

    /**
     * Read a meta key from cart line meta (array or object).
     */
    protected function metaValue(mixed $meta, string $key): mixed
    {
        return match (true) {
            is_array($meta) => $meta[$key] ?? null,
            is_object($meta) => $meta->{$key} ?? null,
            default => null,
        };
    }

    /**
     * Whether meta (array or object) has an explicit key, including null values.
     */
    protected function metaHasKey(mixed $meta, string $key): bool
    {
        return (is_array($meta) && array_key_exists($key, $meta))
            || (is_object($meta) && (property_exists($meta, $key) || isset($meta->{$key})));
    }

    /**
     * Normalize array/object/Traversable values into a plain array.
     *
     * @return array<array-key, mixed>|null
     */
    protected function normalizeToArray(mixed $value): ?array
    {
        $value = match (true) {
            $value instanceof \Traversable => iterator_to_array($value),
            is_object($value) => (array) $value,
            default => $value,
        };

        return is_array($value) ? $value : null;
    }

    /**
     * Coerce cart line meta into a mutable object.
     */
    protected function lineMetaAsObject(CartLine $line): object
    {
        $meta = $line->meta ?? null;

        return match (true) {
            is_array($meta) => (object) $meta,
            is_object($meta) => $meta,
            default => (object) [],
        };
    }
}
