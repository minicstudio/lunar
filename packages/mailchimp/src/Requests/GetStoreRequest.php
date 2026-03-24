<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetStoreRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $storeId
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/ecommerce/stores/{$this->storeId}";
    }
}
