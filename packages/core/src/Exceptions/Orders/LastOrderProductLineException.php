<?php

namespace Lunar\Exceptions\Orders;

class LastOrderProductLineException extends OrderProductLineEditException
{
    public function __construct()
    {
        parent::__construct(['last_line']);
    }
}
