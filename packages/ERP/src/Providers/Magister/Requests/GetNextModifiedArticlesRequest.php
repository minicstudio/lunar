<?php

namespace Lunar\ERP\Providers\Magister\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetNextModifiedArticlesRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * The application ID for the request.
     */
    protected string $appId;

    /**
     * The shop ID for the request.
     */
    protected string $shopId;

    /**
     * Create a new request instance.
     */
    public function __construct()
    {
        $this->appId = config('lunar.erp.magister.app_id');
        $this->shopId = config('lunar.erp.magister.shop_id');
    }

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/GetNextModifiedArticles/{$this->appId}/{$this->shopId}";
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
