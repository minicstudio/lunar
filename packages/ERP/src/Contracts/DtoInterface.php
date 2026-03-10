<?php

namespace Lunar\ERP\Contracts;

interface DtoInterface
{
    /**
     * Returns the DTO as an associative array.
     */
    public function toArray(): array;
}
