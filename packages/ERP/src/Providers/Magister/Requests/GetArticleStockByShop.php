<?php

namespace Lunar\ERP\Providers\Magister\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetArticleStockByShop extends Request
{
    protected Method $method = Method::GET;

    /**
     * The application ID for the request.
     */
    protected string $appId;

    /**
     * The type for the article id
     * 1 - ERP article id, 2 - external id (id in our DB)
     */
    protected string $type = '1';

    /**
     * Create a new request instance.
     */
    public function __construct(protected int $articleId)
    {
        $this->appId = config('lunar.erp.magister.app_id');
        $this->type = 1; // Using ERP article id (IDSMARTCASH)
    }

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/GetArticleStockByShop/{$this->appId}/{$this->type}/{$this->articleId}";
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
