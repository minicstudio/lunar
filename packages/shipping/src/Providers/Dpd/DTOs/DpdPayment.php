<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdPayment
{
    public function __construct(
        public string $courierServicePayer,
        public string $packagePayer,
    ) {}

    public function toArray(): array
    {
        return [
            'courierServicePayer' => $this->courierServicePayer,
            'packagePayer' => $this->packagePayer,
        ];
    }
}
