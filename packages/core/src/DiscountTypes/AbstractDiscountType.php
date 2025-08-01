<?php

namespace Lunar\DiscountTypes;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Lunar\Base\DiscountTypeInterface;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Contracts\Discount as DiscountContract;
use Lunar\Models\Discount;

abstract class AbstractDiscountType implements DiscountTypeInterface
{
    /**
     * The instance of the discount.
     */
    public DiscountContract $discount;

    /**
     * Set the data for the discount to user.
     *
     * @param  array  $data
     */
    public function with(DiscountContract $discount): self
    {
        /** @var Discount $discount */
        $this->discount = $discount;

        return $this;
    }

    /**
     * Mark a discount as used
     */
    public function markAsUsed(CartContract $cart): self
    {
        /** @var Cart $cart */
        $this->discount->uses = $this->discount->uses + 1;

        if ($user = $cart->user) {
            $this->discount->users()->attach($user);
        }

        return $this;
    }

    /**
     * Return the eligible lines for the discount.
     *
     * @return Illuminate\Support\Collection
     */
    protected function getEligibleLines(CartContract $cart): Collection
    {
        /** @var Cart $cart */
        return $cart->lines;
    }

    /**
     * Check if discount's conditions met.
     */
    protected function checkDiscountConditions(CartContract $cart): bool
    {
        /** @var Cart $cart */
        $data = $this->discount->data;

        $customerIds = $this->discount->customers->pluck('id');

        if ((! $customerIds->isEmpty() && ! $cart->customer) || (! $customerIds->isEmpty() && ! $customerIds->contains($cart->customer_id))) {
            return false;
        }

        $cartCoupon = strtoupper($cart->coupon_code ?? '');
        $conditionCoupon = strtoupper($this->discount->coupon ?? '');

        $validCoupon = $cartCoupon ? ($cartCoupon === $conditionCoupon) : blank($conditionCoupon);

        $minSpend = (int) ($data['min_prices'][$cart->currency->code] ?? 0) / (int) $cart->currency->factor;
        $minSpend = (int) bcmul($minSpend, $cart->currency->factor);

        $lines = $this->getEligibleLines($cart);
        $validMinSpend = $minSpend ? $minSpend < $lines->sum('subTotal.value') : true;

        $validMaxUses = $this->discount->max_uses ? $this->discount->uses < $this->discount->max_uses : true;

        if ($validMaxUses && $this->discount->max_uses_per_user) {
            $validMaxUses = $cart->user && ($this->usesByUser($cart->user) < $this->discount->max_uses_per_user);
        }

        return $validCoupon && $validMinSpend && $validMaxUses;
    }

    /**
     * Check if discount's conditions met.
     *
     * @param  Lunar\Base\ValueObjects\Cart\DiscountBreakdown  $breakdown
     * @return self
     */
    protected function addDiscountBreakdown(CartContract $cart, DiscountBreakdown $breakdown)
    {
        /** @var Cart $cart */
        if (! $cart->discountBreakdown) {
            $cart->discountBreakdown = collect();
        }
        $cart->discountBreakdown->push($breakdown);

        return $this;
    }

    /**
     * Check how many times this discount has been used by the logged in user's customers
     *
     * @param  Illuminate\Contracts\Auth\Authenticatable  $user
     * @return int
     */
    protected function usesByUser(Authenticatable $user)
    {
        return $this->discount->users()
            ->whereUserId($user->getKey())
            ->count();
    }
}
