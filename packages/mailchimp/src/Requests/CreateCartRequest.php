<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateCartRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $storeId,
        protected array $data
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/ecommerce/stores/{$this->storeId}/carts";
    }

    /**
     * The request body.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
