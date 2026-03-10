<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdParcelRef
{
    public function __construct(
        public string $id,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
        ];
    }
}
