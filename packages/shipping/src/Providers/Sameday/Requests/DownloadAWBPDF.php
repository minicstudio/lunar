<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\Requests;

use Lunar\Addons\Shipping\Providers\Sameday\SamedayTokenProvider;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class DownloadAWBPDF extends Request
{
    protected Method $method = Method::GET;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $awbNumber,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/api/awb/download/{$this->awbNumber}";
    }

    /**
     * The request headers.
     */
    public function defaultHeaders(): array
    {
        return [
            'X-AUTH-TOKEN' => app(SamedayTokenProvider::class)->getToken(),
        ];
    }
}
