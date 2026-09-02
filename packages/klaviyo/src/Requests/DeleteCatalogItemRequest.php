<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteCatalogItemRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $catalogItemId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-items/'.rawurlencode($this->catalogItemId).'/';
    }
}
