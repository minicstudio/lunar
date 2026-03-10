<?php

namespace Lunar\ERP\Providers\Magister\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SendOrderRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * The application ID for the request.
     */
    protected string $appId;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected array $orderData,
    ) {
        $this->appId = config('lunar.erp.magister.app_id');
    }

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/%22AddNewDeliveryOrder%22/{$this->appId}";
    }

    /**
     * The request headers.
     */
    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * The body to send with the request.
     */
    public function defaultBody(): array
    {
        return $this->orderData;
    }
}
