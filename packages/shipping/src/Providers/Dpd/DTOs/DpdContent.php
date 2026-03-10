<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

class DpdContent
{
    public function __construct(
        public int $parcelsCount,
        public float $totalWeight,
        public string $contents,
        public string $package,
    ) {}

    public function toArray(): array
    {
        return [
            'parcelsCount' => $this->parcelsCount,
            'totalWeight' => $this->totalWeight,
            'contents' => $this->contents,
            'package' => $this->package,
        ];
    }
}
