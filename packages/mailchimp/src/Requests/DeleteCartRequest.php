<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteCartRequest extends Request
{
    protected Method $method = Method::DELETE;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $storeId,
        protected string $cartId
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/ecommerce/stores/{$this->storeId}/carts/{$this->cartId}";
    }
}
