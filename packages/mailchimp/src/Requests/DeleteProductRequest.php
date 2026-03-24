<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteProductRequest extends Request
{
    protected Method $method = Method::DELETE;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $storeId,
        protected string $productId
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/ecommerce/stores/{$this->storeId}/products/{$this->productId}";
    }
}
