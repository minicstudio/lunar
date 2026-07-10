<?php

namespace Lunar\Loyalty\Validation\Cart;

use Lunar\Loyalty\Exceptions\InsufficientLoyaltyPointsException;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Validation\BaseValidator;

final class LoyaltyRedemptionValidator extends BaseValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate(): bool
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return $this->pass();
        }

        $cart = $this->parameters['cart'];
        $redeemer = app(LoyaltyRedeemer::class);
        $points = $redeemer->getPointsFromCart($cart);

        if ($points <= 0) {
            return $this->pass();
        }

        try {
            $redeemer->validateRedemption($cart, $points);
        } catch (InsufficientLoyaltyPointsException $e) {
            return $this->fail('cart', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->fail('cart', $e->getMessage());
        }

        return $this->pass();
    }
}
