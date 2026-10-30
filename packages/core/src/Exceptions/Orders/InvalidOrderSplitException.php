<?php

namespace Lunar\Exceptions\Orders;

class InvalidOrderSplitException extends OrderSplitException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public array $reasons)
    {
        parent::__construct(__('lunar::exceptions.order.invalid_split'));
    }
}
