<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCatalogItemVariantIdsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, scalar|null>  $filters
     */
    public function __construct(
        protected string $catalogItemId,
        protected array $filters = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-items/'.rawurlencode($this->catalogItemId).'/relationships/variants/';
    }

    protected function defaultQuery(): array
    {
        return array_filter(
            $this->filters,
            fn ($value) => $value !== null && $value !== ''
        );
    }
}
