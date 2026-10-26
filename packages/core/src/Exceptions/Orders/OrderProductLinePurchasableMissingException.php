<?php

namespace Lunar\Exceptions\Orders;

class OrderProductLinePurchasableMissingException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['purchasable']);
    }
}
