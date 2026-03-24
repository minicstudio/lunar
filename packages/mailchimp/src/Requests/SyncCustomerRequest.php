<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SyncCustomerRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $storeId,
        protected string $customerId,
        protected array $data
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/ecommerce/stores/{$this->storeId}/customers/{$this->customerId}";
    }

    /**
     * The request body.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
