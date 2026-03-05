<?php

namespace Lunar\Managers;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Lunar\Base\DataTransferObjects\CartDiscount;
use Lunar\Base\DiscountManagerInterface;
use Lunar\Base\Validation\CouponValidator;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Contracts\Channel as ChannelContract;
use Lunar\Models\Contracts\CustomerGroup as CustomerGroupContract;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

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
        // Return the discounts if they are already set, because we use a singleton instance
        if ($this->discounts && $this->discounts->isNotEmpty()) {
            return $this->discounts;
        }

        if ($this->channels->isEmpty() && $defaultChannel = Channel::getDefault()) {
            $this->channel($defaultChannel);
        }

        if ($cart && $customerGroups = $cart->customer?->customerGroups) {
            $this->customerGroup($customerGroups);
        }

        if ($this->customerGroups->isEmpty() && $defaultGroup = CustomerGroup::getDefault()) {
            $this->customerGroup($defaultGroup);
        }

        return Discount::active()
            ->usable()
            ->channel($this->channels)
            ->customerGroup($this->customerGroups)
            ->with([
                'discountables',
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
        if (! $this->discounts || $this->discounts?->isEmpty()) {
            $this->discounts = $this->getDiscounts($cart);
        }

        // Apply automatically applied discounts
        foreach ($cart->lines as $line) {
            // Get the best discount for the line and push it, if it isn't already in the collection
            $discount = $this->filterDiscountsByPriority($this->discounts, $line->purchasable)->first();

            if (! $discount) {
                continue;
            }

            $discount->getType()->applyPercentageForLine($cart, $line);
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
    public function filterDiscountsByPriority($availableDiscounts, null|Product|ProductVariant $purchasable = null): Collection
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
            elseif (! $discount->discountables->count() && ! $discount->collections->count()) {
                $otherDiscounts->push($discount);
            }
        }

        // Return the highest value discount from the highest priority category
        if ($productVariantDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($productVariantDiscounts)]);
        }

        if ($productDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($productDiscounts)]);
        }

        if ($collectionDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($collectionDiscounts)]);
        }

        if ($otherDiscounts->isNotEmpty()) {
            return collect([$this->getHighestValueDiscount($otherDiscounts)]);
        }

        // No discounts found
        return collect();
    }

    /**
     * {@inheritDoc}
     */
    public function getDiscountForPurchasable(null|Product|ProductVariant $purchasable = null): ?Discount
    {
        $discounts = $this->getDiscounts(null);

        if ($discounts->isEmpty()) {
            return null;
        }

        return $this->filterDiscountsByPriority($discounts, $purchasable)->first();
    }

    /**
     * Get the discount with the highest value from a collection
     */
    protected function getHighestValueDiscount($discounts)
    {
        $bestDiscount = null;
        $highestDiscountValue = 0;

        foreach ($discounts as $discount) {
            $data = $discount->data;

            if ($data['percentage'] && $data['percentage'] > $highestDiscountValue) {
                $highestDiscountValue = $data['percentage'];
                $bestDiscount = $discount;
            }
        }

        return $bestDiscount;
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
