<?php

namespace Lunar\ERP\Providers\Magister\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ConfirmReceivingDataRequest extends Request
{
    protected Method $method = Method::POST;

    /**
     * The application ID for the request.
     */
    protected string $appId;

    protected int $shopId;

    /**
     * Create a new request instance.
     */
    public function __construct(protected int $typeOf, protected int $recVersion)
    {
        $this->appId = config('lunar.erp.magister.app_id');
        $this->shopId = config('lunar.erp.magister.shop_id'); // Default to shop ID 1 if not set
    }

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/%22ConfirmReceivingDataByTypeOf%22/{$this->appId}/{$this->typeOf}/{$this->shopId}/{$this->recVersion}";
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
