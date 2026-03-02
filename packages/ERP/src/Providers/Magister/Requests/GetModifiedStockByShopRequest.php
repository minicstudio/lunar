<?php

namespace Lunar\ERP\Providers\Magister\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetModifiedStockByShopRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * The application ID for the request.
     */
    protected string $appId;

    /**
     * Create a new request instance.
     */
    public function __construct()
    {
        $this->appId = config('lunar.erp.magister.app_id');
    }

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/GetNextModifiedStockByShop/{$this->appId}";
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
}
