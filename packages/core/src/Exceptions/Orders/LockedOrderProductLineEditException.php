<?php

namespace Lunar\Exceptions\Orders;

class LockedOrderProductLineEditException extends OrderProductLineEditException
{
    /**
     * @param  list<string>  $gates
     */
    public function __construct(array $gates)
    {
        parent::__construct($gates);
    }
}
