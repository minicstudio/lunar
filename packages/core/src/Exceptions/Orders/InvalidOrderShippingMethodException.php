<?php

namespace Lunar\Exceptions\Orders;

class InvalidOrderShippingMethodException extends OrderProductLineEditException
{
    public function __construct(string $reason = 'shipping_method')
    {
        parent::__construct([$reason]);
    }
}
