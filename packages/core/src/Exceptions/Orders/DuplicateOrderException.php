<?php

namespace Lunar\Exceptions\Orders;

use Lunar\Exceptions\LunarException;

class DuplicateOrderException extends LunarException
{
    public function __construct(public string $reason)
    {
        parent::__construct($reason);
    }
}
