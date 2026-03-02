<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\DTOs;

use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;

class SamedayAWBRequestBody implements AWBRequestBodyInterface
{
    public function __construct(
        public int $pickupPoint,
        public int $packageType,
        public float $packageWeight,
        public int $service,
        public ?array $serviceTaxes,
        public string $awbPayment,
        public float $cashOnDelivery,
        public float $insuredValue,
        public int $thirdPartyPickup,
        public SamedayAWBRecipient $awbRecipient,
        public array $parcels,
        public int $contactPerson,
        public int $packageNumber,
        public string $clientInternalReference,
        public ?string $observation = null,
        public ?int $oohLastMile = null,
    ) {}

    public function toArray(): array
    {
        return [
            'pickupPoint' => $this->pickupPoint,
            'packageType' => $this->packageType,
            'packageWeight' => $this->packageWeight,
            'service' => $this->service,
            'serviceTaxes' => $this->serviceTaxes,
            'awbPayment' => $this->awbPayment,
            'cashOnDelivery' => $this->cashOnDelivery,
            'insuredValue' => $this->insuredValue,
            'thirdPartyPickup' => $this->thirdPartyPickup,
            'awbRecipient' => $this->awbRecipient->toArray(),
            'parcels' => array_map(fn($parcel) => $parcel->toArray(), $this->parcels),
            'contactPerson' => $this->contactPerson,
            'packageNumber' => $this->packageNumber,
            'clientInternalReference' => $this->clientInternalReference,
            'observation' => $this->observation,
            'oohLastMile' => $this->oohLastMile,
        ];
    }
}
