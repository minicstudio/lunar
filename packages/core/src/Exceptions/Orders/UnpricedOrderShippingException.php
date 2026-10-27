<?php

namespace Lunar\Exceptions\Orders;

class UnpricedOrderShippingException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['shipping']);
    }
}
