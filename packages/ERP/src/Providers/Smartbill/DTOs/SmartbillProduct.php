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
        public ?string $taxName,
        public ?float $taxPercentage,
        public bool $saveToDb,
        public bool $isService,
        public ?string $productDescription = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'measuringUnitName' => $this->measuringUnitName,
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];

        // A seller not registered for VAT sends no VAT fields at all.
        if ($this->taxPercentage !== null) {
            $data['isTaxIncluded'] = $this->isTaxIncluded;
            $data['taxName'] = $this->taxName;
            $data['taxPercentage'] = $this->taxPercentage;
        }

        $data['isService'] = $this->isService;

        if (filled($this->productDescription)) {
            $data['productDescription'] = $this->productDescription;
        }

        return $data;
    }
}
