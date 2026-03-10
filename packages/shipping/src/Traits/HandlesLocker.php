<?php

namespace Lunar\Addons\Shipping\Traits;

use Lunar\Addons\Shipping\Enums\ShippingType;

trait HandlesLocker
{
    /**
     * Whether the cart has a locker address given.
     */
    public function isLockerAddress(): bool
    {
        return $this->cart->meta &&
            isset($this->cart->meta['shippingType']) &&
            $this->cart->meta['shippingType'] === ShippingType::LOCKER->value;
    }
}
