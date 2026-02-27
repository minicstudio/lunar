<?php

namespace Lunar\ERP\Providers\Smartbill\DTOs;

use Lunar\ERP\Contracts\DtoInterface;

class SmartbillPrintRequestQuery implements DtoInterface
{
    public function __construct(
        public string $series,
        public string $number,
        public string $companyVatCode,
    ) {}

    public function toArray(): array
    {
        return [
            'seriesname' => $this->series,
            'number' => $this->number,
            'cif' => $this->companyVatCode,
        ];
    }
}
