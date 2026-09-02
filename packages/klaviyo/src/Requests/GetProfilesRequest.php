<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetProfilesRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, string|array<int, string>>  $filters
     */
    public function __construct(
        protected array $filters = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/profiles/';
    }

    protected function defaultQuery(): array
    {
        return $this->filters;
    }
}
