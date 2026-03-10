<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\DTOs;

class SamedayAWBRecipient
{
    public function __construct(
        public string $name,
        public string $phoneNumber,
        public int $personType,
        public ?string $companyName,
        public string $postalCode,
        public string $countyString,
        public string $cityString,
        public string $address,
        public string $email,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phoneNumber' => $this->phoneNumber,
            'personType' => $this->personType,
            'companyName' => $this->companyName,
            'postalCode' => $this->postalCode,
            'countyString' => $this->countyString,
            'cityString' => $this->cityString,
            'address' => $this->address,
            'email' => $this->email,
        ];
    }
}
