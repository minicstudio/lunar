<?php

namespace Lunar\Exceptions\Orders;

class UnauthorizedOrderProductLineEditException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['unauthorized']);
    }
}
