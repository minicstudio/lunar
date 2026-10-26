<?php

namespace Lunar\Exceptions\Orders;

class OrderProductLineNotFoundException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['line']);
    }
}
