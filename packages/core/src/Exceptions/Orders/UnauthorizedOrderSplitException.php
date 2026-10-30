<?php

namespace Lunar\Exceptions\Orders;

class UnauthorizedOrderSplitException extends OrderSplitException
{
    public function __construct()
    {
        parent::__construct(__('lunar::exceptions.order.unauthorized_split'));
    }
}
