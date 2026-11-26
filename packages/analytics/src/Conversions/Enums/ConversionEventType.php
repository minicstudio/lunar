<?php

namespace Lunar\Analytics\Conversions\Enums;

enum ConversionEventType: string
{
    /**
     * An add to cart the storefront reported.
     */
    case AddToCart = 'add_to_cart';

    /**
     * A cart's first reported add to cart.
     */
    case CartStarted = 'cart_started';

    /**
     * A cart's first checkout start.
     */
    case BeginCheckout = 'begin_checkout';
}
