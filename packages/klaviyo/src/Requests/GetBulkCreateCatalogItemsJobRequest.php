<?php

namespace Lunar\Klaviyo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetBulkCreateCatalogItemsJobRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, scalar|null>  $filters
     */
    public function __construct(
        protected string $jobId,
        protected array $filters = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/catalog-item-bulk-create-jobs/'.rawurlencode($this->jobId).'/';
    }

    protected function defaultQuery(): array
    {
        return array_filter(
            $this->filters,
            fn ($value) => $value !== null && $value !== ''
        );
    }
}
