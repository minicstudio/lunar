<?php

namespace Lunar\Addons\Shipping\DTOs;

class LockerDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $address = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public int $countryId = 0,
        public string $county = '',
        public string $city = '',
        public string $postalCode = '',
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'countryId' => $this->countryId,
            'county' => $this->county,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
        ];
    }
}
