<?php

namespace Lunar\ERP\Providers\Smartbill\DTOs;

use Lunar\ERP\Contracts\DtoInterface;

class SmartbillInvoiceRequestBody implements DtoInterface
{
    public function __construct(
        public string $companyVatCode,
        public string $seriesName,
        public SmartbillClient $client,
        public array $products,
    ) {}

    public function toArray(): array
    {
        return [
            'companyVatCode' => $this->companyVatCode,
            'seriesName' => $this->seriesName,
            'client' => $this->client->toArray(),
            'products' => array_map(fn ($product) => $product->toArray(), $this->products),
        ];
    }
}
