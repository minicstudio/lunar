<?php

namespace Lunar\Exceptions\Orders;

class LockedOrderSplitException extends OrderSplitException
{
    /**
     * @param  list<string>  $gates
     */
    public function __construct(public array $gates)
    {
        parent::__construct(__('lunar::exceptions.order.locked_split'));
    }
}
