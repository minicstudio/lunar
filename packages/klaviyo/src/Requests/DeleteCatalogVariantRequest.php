<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteCatalogVariantRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $catalogVariantId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-variants/'.rawurlencode($this->catalogVariantId).'/';
    }
}
