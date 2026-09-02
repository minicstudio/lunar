<?php

namespace Lunar\Loyalty\Exceptions;

use Exception;

class InsufficientLoyaltyPointsException extends Exception
{
    public static function forRequested(int $requested, int $available): self
    {
        return new self("Insufficient loyalty points. Requested {$requested}, available {$available}.");
    }
}
