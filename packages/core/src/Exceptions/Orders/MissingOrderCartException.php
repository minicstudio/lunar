<?php

namespace Lunar\Exceptions\Orders;

class MissingOrderCartException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['cart']);
    }
}
