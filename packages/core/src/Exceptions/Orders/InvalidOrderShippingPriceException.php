<?php

namespace Lunar\Exceptions\Orders;

class InvalidOrderShippingPriceException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['shipping_price']);
    }
}
