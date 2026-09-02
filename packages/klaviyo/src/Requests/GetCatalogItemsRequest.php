<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCatalogItemsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, scalar|null>  $filters
     */
    public function __construct(
        protected array $filters = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-items/';
    }

    protected function defaultQuery(): array
    {
        return array_filter(
            $this->filters,
            fn ($value) => $value !== null && $value !== ''
        );
    }
}
