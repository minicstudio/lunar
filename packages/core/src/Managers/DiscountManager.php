<?php

namespace Lunar\Managers;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Lunar\Base\DataTransferObjects\CartDiscount;
use Lunar\Base\DiscountManagerInterface;
use Lunar\Base\Validation\CouponValidator;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Contracts\CartLine as CartLineContract;
use Lunar\Models\Contracts\Channel as ChannelContract;
use Lunar\Models\Contracts\CustomerGroup as CustomerGroupContract;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Spatie\LaravelBlink\BlinkFacade as Blink;

class DiscountManager implements DiscountManagerInterface
{
    /**
     * The current channels.
     *
     * @var null|Collection<Channel>
     */
    protected ?Collection $channels = null;

    /**
     * The current customer groups
     *
     * @var null|Collection<CustomerGroup>
     */
    protected ?Collection $customerGroups = null;

    /**
     * The available discounts
     */
    protected ?Collection $discounts = null;

    /**
     * Cache key for cart-scoped discount lookups on this manager instance.
     */
    protected ?string $discountsCacheKey = null;

    /**
     * Cached catalog-wide discounts for channel/group scope (no cart filter).
     */
    protected ?Collection $catalogDiscounts = null;

    /**
     * Cache key for {@see $catalogDiscounts}.
     */
    protected ?string $catalogDiscountsCacheKey = null;

    /**
     * Per-purchasable memoized result of getDiscountForPurchasable(), keyed by
     * "<purchasable class>:<purchasable id>".
     *
     * @var array<string, ?Discount>
     */
    protected array $discountForPurchasableCache = [];

    /**
     * The available discount types
     *
     * @var array
     */
    protected $types = [
        // Disabled for security: only AdvancedAmountOff is supported right now.
        // AmountOff::class,
        // BuyXGetY::class,
        AdvancedAmountOff::class,
    ];

    /**
     * The applied discounts.
     */
    protected Collection $applied;

    /**
     * Instantiate the class.
     */
    public function __construct()
    {
        $this->applied = collect();
        $this->channels = collect();
        $this->customerGroups = collect();
    }

    /**
     * Set a single channel or a collection.
     */
    public function channel(ChannelContract|iterable $channel): self
    {
        $channels = collect(
            ! is_iterable($channel) ? [$channel] : $channel
        );

        if ($nonChannel = $channels->filter(fn ($channel) => ! $channel instanceof ChannelContract)->first()) {
            throw new InvalidArgumentException(
                __('lunar::exceptions.discounts.invalid_type', [
                    'expected' => ChannelContract::class,
                    'actual' => $nonChannel->getMorphClass(),
                ])
            );
        }

        $this->channels = $channels;

        return $this;
    }

    /**
     * Set a single customer group or a collection.
     */
    public function customerGroup(CustomerGroupContract|iterable $customerGroups): self
    {
        $customerGroups = collect(
            ! is_iterable($customerGroups) ? [$customerGroups] : $customerGroups
        );

        if ($nonGroup = $customerGroups->filter(fn ($channel) => ! $channel instanceof CustomerGroupContract)->first()) {
            throw new InvalidArgumentException(
                __('lunar::exceptions.discounts.invalid_type', [
                    'expected' => CustomerGroupContract::class,
                    'actual' => $nonGroup->getMorphClass(),
                ])
            );
        }
        $this->customerGroups = $customerGroups;

        return $this;
    }

    /**
     * Return the applied channels.
     */
    public function getChannels(): Collection
    {
        return $this->channels;
    }

    /**
     * Returns the available discounts.
     */
    public function getDiscounts(?Cart $cart = null): Collection
    {
        $this->ensureDiscountChannelsAndGroups($cart);

        $cacheKey = $this->buildDiscountsCacheKey($cart);

        if ($cart) {
            if ($this->discounts !== null && $this->discountsCacheKey === $cacheKey) {
                return $this->discounts;
            }

            $discounts = $this->queryDiscounts($cart);

            $this->discounts = $discounts;
            $this->discountsCacheKey = $cacheKey;

            return $discounts;
        }

        if ($this->discounts && $this->discounts->isNotEmpty()) {
            return $this->discounts;
        }

        return $this->queryDiscounts(null);
    }

    /**
     * Load and cache catalog-wide discounts for the current channel/group scope.
     */
    protected function getCatalogDiscounts(): Collection
    {
        $this->ensureDiscountChannelsAndGroups();

        $cacheKey = $this->buildDiscountsCacheKey(null);

        if ($this->catalogDiscounts !== null && $this->catalogDiscountsCacheKey === $cacheKey) {
            return $this->catalogDiscounts;
        }

        $this->catalogDiscountsCacheKey = $cacheKey;

        return $this->catalogDiscounts = $this->queryDiscounts(null);
    }

    /**
     * Ensure default channel and customer groups are set before discount queries.
     */
    protected function ensureDiscountChannelsAndGroups(?Cart $cart = null): void
    {
        if ($this->channels->isEmpty() && $defaultChannel = Channel::getDefault()) {
            $this->channel($defaultChannel);
        }

        if ($cart && $customer = $cart->customer) {
            $customerGroups = Blink::once('customer_groups_'.$customer->id, function () use ($customer) {
                $customer->loadMissing('customerGroups');

                return $customer->customerGroups;
            });

            if ($customerGroups?->isNotEmpty()) {
                $this->customerGroup($customerGroups);
            }
        }

        if ($this->customerGroups->isEmpty() && $defaultGroup = CustomerGroup::getDefault()) {
            $this->customerGroup($defaultGroup);
        }
    }

    /**
     * Build a stable cache key for discount lookups.
     */
    protected function buildDiscountsCacheKey(?Cart $cart): string
    {
        $cartKey = 'none';

        if ($cart) {
            $productIds = $cart->lines->pluck('purchasable.product_id')->filter()->sort()->values();
            $variantIds = $cart->lines->pluck('purchasable.id')->filter()->sort()->values();

            $cartKey = $cart->id.'_products_'.$productIds->implode(',').'_variants_'.$variantIds->implode(',');
        }

        return 'lunar_discounts_'.
            $this->channels->pluck('id')->sort()->implode(',').'_'.
            $this->customerGroups->pluck('id')->sort()->implode(',').'_'.
            $cartKey;
    }

    /**
     * Query discounts from the database.
     */
    protected function queryDiscounts(?Cart $cart): Collection
    {
        return Discount::active()
            ->usable()
            ->channel($this->channels)
            ->customerGroup($this->customerGroups)
            ->with([
                'discountables',
                'collections',
            ])
            ->when(
                $cart,
                function ($query, $value) {
                    return $query->where(function ($query) use ($value) {

                        return $query->where(fn ($query) => $query->products(
                            $value->lines->pluck('purchasable.product_id')->filter()->values(),
                            ['condition', 'limitation']
                        )
                        )
                            ->orWhere(fn ($query) => $query->productVariants(
                                $value->lines->pluck('purchasable.id')->filter()->values(),
                                ['condition', 'limitation']
                            )
                            )
                            ->orWhere(fn ($query) => $query->collections(
                                $value->lines->map(fn ($line) => $line->purchasable->product->collections->pluck('id'))->flatten()->filter()->values(),
                                ['condition']
                            )
                            );
                    });
                }
            )->orderBy('priority', 'desc')
            ->orderBy('id')
            ->get()
            ->filter(function ($discount) {
                // IMPORTANT: Skip discounts which has no data or data is empty
                // can be a case after creation until the user updates the discount
                if (! $discount->data || empty($discount->data)) {
                    return false;
                }

                return true;
            });
    }

    /**
     * {@inheritDoc}
     */
    public function preloadDiscountsForPurchasables(iterable $purchasables): Collection
    {
        $purchasables = collect($purchasables)->filter();

        $productIds = $purchasables
            ->filter(fn ($purchasable) => $purchasable instanceof Product)
            ->pluck('id')
            ->merge(
                $purchasables
                    ->filter(fn ($purchasable) => $purchasable instanceof ProductVariant)
                    ->pluck('product_id')
            )
            ->filter()
            ->unique()
            ->values();

        $variantIds = $purchasables
            ->filter(fn ($purchasable) => $purchasable instanceof ProductVariant)
            ->pluck('id')
            ->unique()
            ->values();

        if ($this->channels->isEmpty() && $defaultChannel = Channel::getDefault()) {
            $this->channel($defaultChannel);
        }

        if ($this->customerGroups->isEmpty() && $defaultGroup = CustomerGroup::getDefault()) {
            $this->customerGroup($defaultGroup);
        }

        $this->discounts = Discount::active()
            ->usable()
            ->channel($this->channels)
            ->customerGroup($this->customerGroups)
            ->withCount(['discountables', 'collections'])
            ->with([
                'discountables' => function ($query) use ($productIds, $variantIds) {
                    $query->where(
                        fn ($query) => $query->whereIn('discountable_id', $productIds)
                            ->where('discountable_type', Product::morphName())
                    )->orWhere(
                        fn ($query) => $query->whereIn('discountable_id', $variantIds)
                            ->where('discountable_type', ProductVariant::morphName())
                    );
                },
                'collections',
            ])
            ->orderBy('priority', 'desc')
            ->orderBy('id')
            ->get()
            ->filter(function ($discount) {
                // IMPORTANT: Skip discounts which has no data or data is empty
                // can be a case after creation until the user updates the discount
                if (! $discount->data || empty($discount->data)) {
                    return false;
                }

                return true;
            });

        $this->discountForPurchasableCache = [];

        return $this->discounts;
    }

    /**
     * Return the applied customer groups.
     */
    public function getCustomerGroups(): Collection
    {
        return $this->customerGroups;
    }

    public function addType($classname): self
    {
        $this->types[] = $classname;

        return $this;
    }

    public function getTypes(): Collection
    {
        return collect($this->types)->map(function ($class) {
            return app($class);
        });
    }

    public function addApplied(CartDiscount $cartDiscount): self
    {
        $this->applied->push($cartDiscount);

        return $this;
    }

    public function getApplied(): Collection
    {
        return $this->applied;
    }

    public function apply(CartContract $cart): CartContract
    {
        $this->discounts = $this->getDiscounts($cart);

        // Apply automatically applied discounts
        foreach ($cart->lines as $line) {
            // Get the best discount for the line and push it, if it isn't already in the collection
            $discount = $this->filterDiscountsByPriority($this->discounts, $line->purchasable, $line)->first();

            if (! $discount) {
                continue;
            }

            if ($discount->data['fixed_value'] ?? false) {
                $discount->getType()->applyFixedValueForLine($cart, $line);
            } else {
                $discount->getType()->applyPercentageForLine($cart, $line);
            }
        }

        // Apply manually applied coupon discount
        if ($cart->coupon_code) {
            $discount = $this->discounts->firstWhere('coupon', $cart->coupon_code);

            if ($discount) {
                $discount->getType()->applyCouponForCart($cart);
            }
        }

        return $cart;
    }

    public function resetDiscounts(): self
    {
        $this->discounts = null;
        $this->discountsCacheKey = null;
        $this->catalogDiscounts = null;
        $this->catalogDiscountsCacheKey = null;

        return $this;
    }

    public function validateCoupon(string $coupon): bool
    {
        return app(
            config('lunar.discounts.coupon_validator', CouponValidator::class)
        )->validate($coupon);
    }

    /**
     * Filter discounts based on priority: variant, product, collection, anything left, exclude coupons
     * Returns the discount with the highest value within each priority level
     */
    public function filterDiscountsByPriority($availableDiscounts, null|Product|ProductVariant $purchasable = null, ?CartLineContract $cartLine = null): Collection
    {
        $productVariantDiscounts = collect();
        $productDiscounts = collect();
        $collectionDiscounts = collect();
        $otherDiscounts = collect();

        // Categorize discounts by priority
        foreach ($availableDiscounts as $discount) {
            // Skip coupon discounts
            if (! empty($discount->coupon)) {
                continue;
            }

            // Priority 1: Check if discount applies to this specific product/variant
            if ($this->discountAppliesToProductVariant($discount, $purchasable)) {
                $productVariantDiscounts->push($discount);
            }
            // Priority 2: Check if discount applies to this specific product
            elseif ($this->discountAppliesToProduct($discount, $purchasable)) {
                $productDiscounts->push($discount);
            }
            // Priority 3: Check if discount applies to collections containing this product
            elseif ($this->discountAppliesToCollection($discount, $purchasable)) {
                $collectionDiscounts->push($discount);
            }
            // Priority 4: Any other applicable discounts which has no discountables or collections attached
            elseif (! ($discount->discountables_count ?? $discount->discountables->count()) && ! ($discount->collections_count ?? $discount->collections->count())) {
                $otherDiscounts->push($discount);
            }
        }

        // Return the highest value discount from the highest priority category
        if ($productVariantDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($productVariantDiscounts, $cartLine, $purchasable)]);
        }

        if ($productDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($productDiscounts, $cartLine, $purchasable)]);
        }

        if ($collectionDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($collectionDiscounts, $cartLine, $purchasable)]);
        }

        if ($otherDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($otherDiscounts, $cartLine, $purchasable)]);
        }

        // No discounts found
        return collect();
    }

    /**
     * {@inheritDoc}
     */
    public function getDiscountForPurchasable(null|Product|ProductVariant $purchasable = null): ?Discount
    {
        if (! $purchasable) {
            return null;
        }

        $cacheKey = get_class($purchasable).':'.$purchasable->getKey();

        if (array_key_exists($cacheKey, $this->discountForPurchasableCache)) {
            return $this->discountForPurchasableCache[$cacheKey];
        }

        $discounts = $this->getCatalogDiscounts();

        if ($discounts->isEmpty()) {
            return $this->discountForPurchasableCache[$cacheKey] = null;
        }

        return $this->discountForPurchasableCache[$cacheKey] = $this->filterDiscountsByPriority($discounts, $purchasable)->first();
    }

    /**
     * Return the discount with the highest estimated monetary value from a
     * collection. Estimates against the cart line if provided, otherwise
     * against the purchasable's original price.
     */
    protected function getHighestValueDiscount($discounts, ?CartLineContract $cartLine = null, null|Product|ProductVariant $purchasable = null)
    {
        $bestDiscount = null;
        $highestDiscountValue = 0;

        foreach ($discounts as $discount) {
            $data = $discount->data;

            $value = $cartLine
                ? $this->estimateDiscountAmountPerUnitForLine($discount, $data, $cartLine)
                : $this->estimateDiscountValueForPurchasable($discount, $data, $purchasable);

            if ($value > $highestDiscountValue) {
                $highestDiscountValue = $value;
                $bestDiscount = $discount;
            }
        }

        return $bestDiscount;
    }

    /**
     * Estimate the per-unit monetary amount a discount would deduct from a
     * given cart line, regardless of whether it's a percentage or fixed-value
     * discount.
     */
    protected function estimateDiscountAmountPerUnitForLine($discount, array $data, CartLineContract $cartLine): float
    {
        $quantity = max(1, $cartLine->quantity);

        if ($data['fixed_value'] ?? false) {
            return (float) $discount->getType()->estimateFixedValueAmountForLine($cartLine) / $quantity;
        }

        $subTotal = $cartLine->subTotalDiscounted?->value ?? $cartLine->subTotal->value;

        return ($subTotal * (($data['percentage'] ?? 0) / 100)) / $quantity;
    }

    /**
     * Estimate the monetary amount a discount would deduct from a purchasable's
     * original price, for the purposes of ranking mixed-type candidates when no
     * specific cart line is available (e.g. catalog/product listing context).
     */
    protected function estimateDiscountValueForPurchasable($discount, array $data, null|Product|ProductVariant $purchasable = null): float
    {
        if (! $purchasable) {
            return 0.0;
        }

        $originalPrices = prices_inc_tax()
            ? $purchasable->getOriginalPricesIncTax()
            : $purchasable->getOriginalPrices();

        $price = $originalPrices->firstWhere('currency.id', StorefrontSession::getCurrency()?->id)
            ?? $originalPrices->first();

        if (! $price) {
            return 0.0;
        }

        if (! is_string($discount->type) || ! class_exists($discount->type) || ! method_exists($discount->type, 'calculateDiscountedPrice')) {
            return 0.0;
        }

        $discountType = $discount->type;

        $discountedPrice = $discountType::calculateDiscountedPrice($price, $data, $discount->coupon ?? null);

        return (float) max(0, $price->value - $discountedPrice);
    }

    /**
     * Check if discount can be applied directly to this product
     */
    protected function discountAppliesToProduct($discount, null|Product|ProductVariant $purchasable = null): bool
    {

        $discountables = $discount->discountables ?? collect();

        foreach ($discountables as $discountable) {
            // Check if discount applies to this specific product
            if ($purchasable && $discountable->discountable_type === Product::morphName() &&
                $discountable->discountable_id === $purchasable->product->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if discount can be applied to this product variant
     */
    protected function discountAppliesToProductVariant($discount, null|Product|ProductVariant $purchasable = null): bool
    {
        $discountables = $discount->discountables ?? collect();

        foreach ($discountables as $discountable) {
            if ($purchasable && $discountable->discountable_type === ProductVariant::morphName() &&
                $discountable->discountable_id === $purchasable->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if discount has a collection limitation and if it applies to the product or variant
     */
    protected function discountAppliesToCollection($discount, null|Product|ProductVariant $purchasable = null): bool
    {
        // Get product collections safely
        $productCollections = collect();
        if ($purchasable && $purchasable instanceof Product) {
            $productCollections = $purchasable->collections->pluck('id');
        } elseif ($purchasable && $purchasable instanceof ProductVariant) {
            $productCollections = $purchasable->product->collections->pluck('id');
        }

        // Check if the product collections intersect with the discount collections and return true if it does
        return $productCollections->intersect($discount->collections->pluck('id'))->count() > 0;
    }
}
