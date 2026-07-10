<?php

namespace Lunar\Loyalty\Services;

use Lunar\Loyalty\Exceptions\InsufficientLoyaltyPointsException;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;

final class LoyaltyRedeemer
{
    public function __construct(
        protected LoyaltyAccountManager $accountManager,
    ) {}

    /**
     * Apply loyalty points redemption to a cart.
     */
    public function applyToCart(CartContract|Cart $cart, int $points): CartContract|Cart
    {
        $this->validateRedemption($cart, $points);

        $discountMinor = $this->calculateDiscountMinor($points);
        $meta = $cart->meta?->toArray() ?? (array) ($cart->meta ?? []);

        $meta['loyalty_points_to_redeem'] = $points;
        $meta['loyalty_discount_minor'] = $discountMinor;

        $cart->update(['meta' => $meta]);

        return $cart->refresh()->calculate();
    }

    /**
     * Clear loyalty redemption from a cart.
     */
    public function clearFromCart(CartContract|Cart $cart): CartContract|Cart
    {
        $this->clearCartMeta($cart);

        return $cart->refresh()->calculate();
    }

    /**
     * Remove loyalty redemption meta without recalculating the cart.
     */
    public function clearCartMeta(CartContract|Cart $cart): void
    {
        $meta = $cart->meta?->toArray() ?? (array) ($cart->meta ?? []);

        unset($meta['loyalty_points_to_redeem'], $meta['loyalty_discount_minor']);

        $cart->update(['meta' => $meta]);
    }

    /**
     * Get the minimum points required for redemption.
     */
    public function getMinRedeemPoints(): int
    {
        return (int) config('lunar.loyalty.currency.min_redeem', 100);
    }

    /**
     * Validate a redemption request.
     */
    public function validateRedemption(CartContract|Cart $cart, int $points): void
    {
        if (! config('lunar.loyalty.enabled', true)) {
            throw new \InvalidArgumentException('Loyalty is not enabled.');
        }

        if (! $cart->customer_id) {
            throw new \InvalidArgumentException('A customer is required to redeem loyalty points.');
        }

        $minRedeem = (int) config('lunar.loyalty.currency.min_redeem', 100);

        if ($points < $minRedeem) {
            throw new \InvalidArgumentException("Minimum redemption is {$minRedeem} points.");
        }

        $account = $this->accountManager->findForCustomer($cart->customer);

        if (! $account) {
            throw InsufficientLoyaltyPointsException::forRequested($points, 0);
        }

        $available = $account->getAvailableBalance();

        if ($points > $available) {
            throw InsufficientLoyaltyPointsException::forRequested($points, $available);
        }

        $discountMinor = $this->calculateDiscountMinor($points);
        $eligibleSubtotal = $this->getEligibleSubtotal($cart);
        $maxPercent = (int) config('lunar.loyalty.currency.max_redeem_percent', 50);
        $maxDiscount = (int) floor($eligibleSubtotal * $maxPercent / 100);

        if ($discountMinor > $maxDiscount) {
            throw new \InvalidArgumentException('Redemption exceeds maximum allowed discount percentage.');
        }
    }

    /**
     * Calculate the discount amount in minor units for the given points.
     */
    public function calculateDiscountMinor(int $points): int
    {
        $ratio = (int) config('lunar.loyalty.currency.redeem_ratio', 1);

        return $points * $ratio;
    }

    /**
     * Get the eligible subtotal for max redemption percent calculation.
     */
    public function getEligibleSubtotal(CartContract|Cart $cart): int
    {
        $cart->loadMissing('lines');

        return (int) $cart->lines->sum(function ($line) {
            return $line->subTotalDiscounted?->value ?? $line->subTotal?->value ?? 0;
        });
    }

    /**
     * Get the points to redeem from cart meta.
     */
    public function getPointsFromCart(CartContract|Cart $cart): int
    {
        $meta = $cart->meta?->toArray() ?? (array) ($cart->meta ?? []);

        return (int) ($meta['loyalty_points_to_redeem'] ?? 0);
    }
}
