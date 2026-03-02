<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdPhoneNumber
{
    public function __construct(
        public string $number,
    ) {}

    public function toArray(): array
    {
        return [
            'number' => $this->number,
        ];
    }
}
