<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateMergeFieldRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $listId,
        protected int $mergeId,
        protected array $data
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/lists/{$this->listId}/merge-fields/{$this->mergeId}";
    }

    /**
     * The request body.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
