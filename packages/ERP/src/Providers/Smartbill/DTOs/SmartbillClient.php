<?php

namespace Lunar\ERP\Providers\Smartbill\DTOs;

use Lunar\ERP\Contracts\DtoInterface;

class SmartbillClient implements DtoInterface
{
    public function __construct(
        public string $name,
        public string $vatCode,
        public bool $isTaxPayer,
        public string $address,
        public string $city,
        public string $county,
        public string $country,
        public string $email,
        public bool $saveToDb,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'vatCode' => $this->vatCode,
            'isTaxPayer' => $this->isTaxPayer,
            'address' => $this->address,
            'city' => $this->city,
            'county' => $this->county,
            'country' => $this->country,
            'email' => $this->email,
            'saveToDb' => $this->saveToDb,
        ];
    }
}
