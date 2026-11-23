<?php

namespace Lunar\Actions\Orders\DTOs;

/**
 * One shipping method the duplicate modal can offer. The amount is minor units.
 */
final class DuplicateOrderShippingMethod
{
    public function __construct(
        public string $identifier,
        public string $name,
        public ?string $option,
        public int $amount,
        public bool $collect,
    ) {}
}
