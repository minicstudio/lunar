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

        $productCollectionIds = collect();

        if ($hasCollectionDiscountables) {
            $products = $cart->lines->map(fn ($line) => $line->purchasable->product)->unique('id');
            $products->loadMissing('collections');
            $productCollectionIds = $products->mapWithKeys(fn ($p) => [$p->id => $p->collections->pluck('id')]);
        }

        // Get all discountables that are eligible.
        $conditions = $cart->lines->reject(function ($line) use ($productCollectionIds) {
            return ! $this->discount->discountableConditions->first(function ($item) use ($line, $productCollectionIds) {
                if ($item->discountable_type == Product::morphName() &&
                    $item->discountable_id == $line->purchasable->product->id
                ) {
                    return true;
                }

                if ($item->discountable_type == ProductVariant::morphName() &&
                    $item->discountable_id == $line->purchasable->id
                ) {
                    return true;
                }

                if ($item->discountable_type == LunarCollection::morphName() &&
                    ($productCollectionIds->get($line->purchasable->product->id) ?? collect())->contains($item->discountable_id)
                ) {
                    return true;
                }

                return false;
            });
        });

        $totalQuantity = $conditions->sum('quantity');

        if (! $conditions->count() || ($minQty && $totalQuantity < $minQty)) {
            return $cart;
        }

        // How many products are rewarded?
        $totalRewardQty = $this->getRewardQuantity(
            $totalQuantity,
            $minQty,
            $rewardQty,
            $maxRewardQty
        );

        if (! $totalRewardQty) {
            return $cart;
        }

        $remainingRewardQty = $totalRewardQty;

        $affectedLines = collect();
        $discountTotal = 0;

        // Get the reward lines and sort by cheapest first.
        $rewardLines = $cart->lines->filter(function ($line) use ($productCollectionIds) {
            return $this->discount->discountableRewards->first(function ($item) use ($line, $productCollectionIds) {
                if ($item->discountable_type == Product::morphName() &&
                    $item->discountable_id == $line->purchasable->product->id
                ) {
                    return true;
                }

                if ($item->discountable_type == ProductVariant::morphName() &&
                    $item->discountable_id == $line->purchasable->id
                ) {
                    return true;
                }

                if ($item->discountable_type == LunarCollection::morphName() &&
                    ($productCollectionIds->get($line->purchasable->product->id) ?? collect())->contains($item->discountable_id)
                ) {
                    return true;
                }

                return false;
            });
        })->sortBy('unitPrice.value');

        foreach ($rewardLines as $rewardLine) {
            if (! $remainingRewardQty) {
                continue;
            }

            $remainder = (int) floor($remainingRewardQty);
            $qtyToAllocate = $remainder;

            if ($rewardLine->quantity < $remainder) {
                $remainder = $rewardLine->quantity % $remainingRewardQty;
                $qtyToAllocate = (int) round(($remainingRewardQty - $remainder) / $rewardLine->quantity);
            }

            if ($rewardLine->quantity == 1 && $remainder) {
                $qtyToAllocate = 1;
                $remainder = $remainder - 1;
            }

            if (! $qtyToAllocate) {
                continue;
            }

            $affectedLines->push(new DiscountBreakdownLine(
                line: $rewardLine,
                quantity: $qtyToAllocate
            ));

            $conditionQtyToAllocate = $qtyToAllocate * ($minQty - $rewardQty);

            $conditions->each(function ($conditionLine) use ($affectedLines, &$conditionQtyToAllocate) {
                if (! $conditionQtyToAllocate) {
                    return;
                }

                $qtyCanBeApplied = min($conditionQtyToAllocate, $conditionLine->quantity - ($affectedLines->firstWhere('line', $conditionLine)?->quantity ?? 0));
                if ($qtyCanBeApplied > 0) {
                    $conditionQtyToAllocate -= $qtyCanBeApplied;

                    $affectedLines->push(new DiscountBreakdownLine(
                        line: $conditionLine,
                        quantity: $qtyCanBeApplied
                    ));
                }
            });

            $remainingRewardQty -= $qtyToAllocate;

            $subTotal = $rewardLine->subTotal->value;

            $unitPrice = $rewardLine->unitPrice->value;

            $lineDiscountTotal = $unitPrice * $qtyToAllocate;
            $discountTotal += $lineDiscountTotal;

            $rewardLine->discountTotal = new Price(
                $lineDiscountTotal,
                $cart->currency,
                1
            );

            $rewardLine->subTotalDiscounted = new Price(
                $subTotal - $lineDiscountTotal,
                $cart->currency,
                1
            );

            if (! $cart->freeItems) {
                $cart->freeItems = collect();
            }

            $cart->freeItems->push($rewardLine->purchasable);
        }

        if ($automaticallyAddRewards) {
            [$affectedLines, $discountTotal] = $this->processAutomaticRewards($cart, $totalRewardQty, $affectedLines, $discountTotal);
        }

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($discountTotal, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        $cart->discounts->push($this);

        return $cart;
    }

    /**
     * Auto-add every fulfillable configured reward (product/variant), using
     * $rewardQty units each. Collection rewards still pick one fulfillable
     * product from the collection.
     */
    private function processAutomaticRewards(CartContract $cart, int $rewardQty, Collection $affectedLines, int $discountTotal)
    {
        if ($rewardQty <= 0) {
            return [$affectedLines, $discountTotal];
        }

        $fulfillableCollectionProducts = [];

        $fulfillableRewards = $this->discount->discountableRewards->filter(function ($discountableReward) use (&$fulfillableCollectionProducts) {
            $rewardItem = $discountableReward->discountable;

            if (! $rewardItem) {
                return false;
            }

            if ($rewardItem instanceof LunarCollection) {
                $fulfillableCollectionProducts[$rewardItem->id] = $rewardItem->products()
                    ->with('variants')
                    ->get()
                    ->filter(fn ($p) => $p->variants->first()?->canBeFulfilledAtQuantity(1))
                    ->values();

                return $fulfillableCollectionProducts[$rewardItem->id]->isNotEmpty();
            }

            if ($rewardItem instanceof Purchasable) {
                return $rewardItem->canBeFulfilledAtQuantity(1);
            }

            return (bool) $rewardItem->variants->first()?->canBeFulfilledAtQuantity(1);
        });

        if ($fulfillableRewards->isEmpty()) {
            return [$affectedLines, $discountTotal];
        }

        foreach ($fulfillableRewards as $discountableReward) {
            $selectedRewardItem = $discountableReward->discountable;

            if ($selectedRewardItem instanceof LunarCollection) {
                $product = $fulfillableCollectionProducts[$selectedRewardItem->id]->random();
                $purchasable = $product->variants->first();
                $selectedRewardItem = $product;
            } elseif ($selectedRewardItem instanceof Purchasable) {
                $purchasable = $selectedRewardItem;
            } else {
                $purchasable = $selectedRewardItem->variants->first();
            }

            if (! $purchasable) {
                continue;
            }

            $qtyToAdd = 0;
            for ($i = 1; $i <= $rewardQty; $i++) {
                if ($purchasable->canBeFulfilledAtQuantity($i)) {
                    $qtyToAdd = $i;
                } else {
                    break;
                }
            }

            if ($qtyToAdd < 1) {
                continue;
            }

            $rewardLine = $cart->lines->first(function ($line) use ($purchasable) {
                return $line->purchasable_id == $purchasable->id
                    && $line->purchasable_type == $purchasable->getMorphClass();
            });

            // Already in the cart: the earlier reward-line pass discounts it.
            // Only create missing rewards here so every configured reward appears.
            if ($rewardLine) {
                continue;
            }

            $rewardLine = $cart->lines()->make([
                'purchasable_type' => $purchasable->getMorphClass(),
                'purchasable_id' => $purchasable->id,
                'quantity' => $qtyToAdd,
            ]);

            if (! $cart->freeItems) {
                $cart->freeItems = collect();
            }

            if (! $cart->freeItems->contains($selectedRewardItem)) {
                $cart->freeItems->push($selectedRewardItem);
            }

            $rewardLine = app(Pipeline::class)
                ->send($rewardLine)
                ->through(
                    config('lunar.cart.pipelines.cart_lines', [])
                )->thenReturn(function ($cartLine) {
                    $cartLine->cacheProperties();

                    return $cartLine;
                });

            $unitQuantity = $purchasable->getUnitQuantity();
            $lineTotal = $rewardLine->unitPrice->value * $rewardLine->quantity;
            $rewardLine->subTotal = new Price($lineTotal, $cart->currency, $unitQuantity);
            $rewardLine->taxAmount = new Price(0, $cart->currency, $unitQuantity);
            $rewardLine->total = new Price($lineTotal, $cart->currency, $unitQuantity);

            $meta = $rewardLine->meta ?? json_decode('{}');
            if (is_array($meta)) {
                $meta = (object) $meta;
            }
            if (! isset($meta->added_by_discount)) {
                $meta->added_by_discount = [];
            }
            if (is_array($meta->added_by_discount)) {
                $meta->added_by_discount = (object) $meta->added_by_discount;
            }

            $meta->added_by_discount->{$this->discount->id} = $qtyToAdd;

            $giftQty = min($qtyToAdd, (int) $rewardLine->quantity);
            $lineDiscountTotal = $rewardLine->unitPrice->value * $giftQty;
            $discountTotal += $lineDiscountTotal;

            $affectedLines->push(new DiscountBreakdownLine(
                line: $rewardLine,
                quantity: $giftQty
            ));

            $rewardLine->discountTotal = new Price(
                $lineDiscountTotal,
                $cart->currency,
                1
            );

            $rewardLine->subTotalDiscounted = new Price(
                max(0, $rewardLine->subTotal->value - $rewardLine->discountTotal->value),
                $cart->currency,
                1
            );

            $rewardLine->meta = $meta;
            $rewardLine->save();

            $cart->setRelation('lines', $cart->lines->push($rewardLine));
        }

        return [$affectedLines, $discountTotal];
    }
}
