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
     * @param  array<string, mixed>  $variant  JSON:API catalog-variant resource
     */
    public function __construct(
        protected array $variant,
    ) {}

    public function resolveEndpoint(): string
    {
        $id = $this->variant['id'] ?? '';

        return '/catalog-variants/'.rawurlencode($id).'/';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'data' => $this->variant,
        ];
    }
}
