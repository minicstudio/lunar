<?php

namespace Lunar\Addons\Shipping\Enums;

enum ShippingProviderEnum: string
{
    case sameday = 'sameday';
    case dpd = 'dpd';
    case fan = 'fan';
    case pickup = 'pickup';
    case inhouse = 'inhouse';

    /**
     * Create an enum instance from an identifier, extracting the base provider name first.
     */
    public static function fromIdentifier(string $identifier): self
    {
        return self::from(preg_split('/[_-]/', $identifier)[0]);
    }
}
