<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\DTOs;

class SamedayParcel
{
    public function __construct(
        public float $weight,
        public ?int $height = null,
        public ?int $width = null,
        public ?int $length = null,
    ) {}

    public function toArray(): array
    {
        return [
            'weight' => $this->weight,
            'height' => $this->height,
            'width' => $this->width,
            'length' => $this->length,
        ];
    }
}
