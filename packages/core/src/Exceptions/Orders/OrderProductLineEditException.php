<?php

namespace Lunar\Exceptions\Orders;

use Lunar\Exceptions\LunarException;

class OrderProductLineEditException extends LunarException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public array $reasons)
    {
        parent::__construct('The order product lines could not be saved.');
    }
}
