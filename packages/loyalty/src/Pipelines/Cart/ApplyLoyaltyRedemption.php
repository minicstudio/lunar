<?php

namespace Lunar\Loyalty\Pipelines\Cart;

use Closure;
use Lunar\DataTypes\Price;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;

final class ApplyLoyaltyRedemption
{
    public function __construct(
        protected LoyaltyRedeemer $redeemer,
    ) {}

    /**
     * Apply loyalty discount to cart lines before tax calculation.
     *
     * @param  Closure(CartContract): mixed  $next
     */
    public function handle(CartContract $cart, Closure $next): mixed
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return $next($cart);
        }

        /** @var Cart $cart */
        $points = $this->redeemer->getPointsFromCart($cart);

        if ($points <= 0) {
            return $next($cart);
        }

        $discountMinor = $this->redeemer->calculateDiscountMinor($points);
        $eligibleSubtotal = $this->redeemer->getEligibleSubtotal($cart);
        $maxPercent = (int) config('lunar.loyalty.currency.max_redeem_percent', 50);
        $maxDiscount = (int) floor($eligibleSubtotal * $maxPercent / 100);
        $cappedDiscount = min($discountMinor, $maxDiscount);
        $minRedeem = $this->redeemer->getMinRedeemPoints();

        if ($cappedDiscount <= 0) {
            $this->redeemer->clearCartMeta($cart);

            return $next($cart);
        }

        if ($cappedDiscount < $discountMinor) {
            $ratio = max(1, (int) config('lunar.loyalty.currency.redeem_ratio', 1));
            $points = (int) floor($cappedDiscount / $ratio);
            $cappedDiscount = $points * $ratio;

            if ($points < $minRedeem) {
                $this->redeemer->clearCartMeta($cart);

                return $next($cart);
            }

            $meta = $cart->meta?->toArray() ?? [];
            $meta['loyalty_points_to_redeem'] = $points;
            $meta['loyalty_discount_minor'] = $cappedDiscount;
            $cart->update(['meta' => $meta]);
        }

        $discountMinor = $cappedDiscount;

        $this->distributeDiscount($cart, $discountMinor);

        return $next($cart);
    }

    /**
     * Distribute a cart-level discount across lines proportionally.
     *
     * Works over the eligible (non-zero subtotal) subset so the last eligible
     * line always absorbs any integer rounding remainder.
     */
    protected function distributeDiscount(Cart $cart, int $discountMinor): void
    {
        $eligibleSubtotal = $this->redeemer->getEligibleSubtotal($cart);

        if ($eligibleSubtotal <= 0) {
            return;
        }

        $eligibleLines = $cart->lines->values()->filter(function ($line) {
            return ($line->subTotalDiscounted?->value ?? $line->subTotal?->value ?? 0) > 0;
        })->values();

        $remaining = $discountMinor;
        $lastIndex = $eligibleLines->count() - 1;

        foreach ($eligibleLines as $index => $line) {
            $lineSubtotal = $line->subTotalDiscounted?->value ?? $line->subTotal?->value ?? 0;

            $lineDiscount = ($index === $lastIndex)
                ? $remaining
                : (int) floor($discountMinor * ($lineSubtotal / $eligibleSubtotal));

            $remaining -= $lineDiscount;

            if ($lineDiscount <= 0) {
                continue;
            }

            $newSubtotal = max(0, $lineSubtotal - $lineDiscount);
            $line->subTotalDiscounted = new Price($newSubtotal, $cart->currency, 1);
            $line->discountTotal = new Price(
                ($line->discountTotal?->value ?? 0) + $lineDiscount,
                $cart->currency,
                1
            );
        }
    }
}
