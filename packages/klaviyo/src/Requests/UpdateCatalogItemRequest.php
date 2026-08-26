<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateCatalogItemRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected string $catalogItemId,
        protected array $data,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-items/'.rawurlencode($this->catalogItemId).'/';
    }

    protected function defaultBody(): array
    {
        return $this->data;
    }
}
