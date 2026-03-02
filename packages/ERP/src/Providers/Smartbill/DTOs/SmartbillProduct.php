<?php

namespace Lunar\ERP\Providers\Smartbill\DTOs;

use Lunar\ERP\Contracts\DtoInterface;

class SmartbillProduct implements DtoInterface
{
    public function __construct(
        public string $name,
        public string $code,
        public string $measuringUnitName,
        public string $currency,
        public float $quantity,
        public float $price,
        public bool $isTaxIncluded,
        public string $taxName,
        public float $taxPercentage,
        public bool $saveToDb,
        public bool $isService,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'measuringUnitName' => $this->measuringUnitName,
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'isTaxIncluded' => $this->isTaxIncluded,
            'taxName' => $this->taxName,
            'taxPercentage' => $this->taxPercentage,
            'isService' => $this->isService,
        ];
    }
}
