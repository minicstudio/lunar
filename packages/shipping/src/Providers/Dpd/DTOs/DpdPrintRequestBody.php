<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdPrintRequestBody
{
    public function __construct(
        public string $userName,
        public string $password,
        public string $paperSize,
        public array $parcels,
    ) {}

    public function toArray(): array
    {
        return [
            'userName' => $this->userName,
            'password' => $this->password,
            'paperSize' => $this->paperSize,
            'parcels' => array_map(fn ($parcel) => $parcel->toArray(), $this->parcels),
        ];
    }
}
