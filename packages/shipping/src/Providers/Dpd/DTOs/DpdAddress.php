<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdAddress
{
    public function __construct(
        public ?string $siteName = null,
        public ?string $postCode = null,
        public ?string $addressNote = null,
    ) {}

    public function toArray(): array
    {
        return [
            'siteName' => $this->siteName,
            'postCode' => $this->postCode,
            'addressNote' => $this->addressNote,
        ];
    }
}
