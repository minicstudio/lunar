<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdRecipient
{
    public function __construct(
        public DpdPhoneNumber $phone,
        public string $clientName,
        public ?string $contactName,
        public ?string $email,
        public bool $privatePerson,
        public DpdAddress $address,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'phone1' => $this->phone->toArray(),
            'clientName' => $this->clientName,
            'contactName' => $this->contactName,
            'email' => $this->email,
            'privatePerson' => $this->privatePerson,
            'address' => $this->address->toArray(),
        ], function ($value) {
            return $value !== null;
        });
    }
}
