<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateCatalogVariantRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected string $catalogVariantId,
        protected array $data,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-variants/'.rawurlencode($this->catalogVariantId).'/';
    }

    protected function defaultBody(): array
    {
        return $this->data;
    }
}
