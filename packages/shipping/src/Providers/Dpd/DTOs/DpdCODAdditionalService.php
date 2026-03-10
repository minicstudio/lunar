<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdCODAdditionalService
{
    public function __construct(
        public float $amount,
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
        ];
    }
}
