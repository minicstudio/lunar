<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdAdditionalServices
{
    public function __construct(
        public DpdCODAdditionalService $cod,
    ) {}

    public function toArray(): array
    {
        return [
            'cod' => $this->cod->toArray(),
        ];
    }
}
