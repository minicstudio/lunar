<?php

namespace Lunar\Addons\Shipping\Contracts;

interface AWBRequestBodyInterface
{
    /**
     * Convert the AWB request body to an array.
     */
    public function toArray(): array;
}
