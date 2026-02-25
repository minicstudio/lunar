<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdParcelToPrint
{
    public function __construct(
        public DpdParcelRef $parcel,
    ) {}

    public function toArray(): array
    {
        return [
            'parcel' => $this->parcel->toArray(),
        ];
    }
}
