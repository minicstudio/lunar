<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdService
{
    public function __construct(
        public bool $autoAdjustPickupDate,
        public DpdAdditionalServices $additionalServices,
        public int $serviceId,
    ) {}

    public function toArray(): array
    {
        return [
            'autoAdjustPickupDate' => $this->autoAdjustPickupDate,
            'serviceId' => $this->serviceId,
            'additionalServices' => $this->additionalServices->toArray(),
        ];
    }
}
