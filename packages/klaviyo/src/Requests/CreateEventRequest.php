<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateEventRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected array $data,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/events/';
    }

    protected function defaultBody(): array
    {
        return $this->data;
    }
}
