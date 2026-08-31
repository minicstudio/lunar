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
     * @param  array<string, mixed>  $item  JSON:API catalog-item resource (type, id, attributes, relationships)
     */
    public function __construct(
        protected array $item,
    ) {}

    public function resolveEndpoint(): string
    {
        $id = $this->item['id'] ?? '';

        return '/catalog-items/'.rawurlencode($id).'/';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'data' => $this->item,
        ];
    }
}
