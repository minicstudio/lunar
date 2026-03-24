<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteMergeFieldRequest extends Request
{
    protected Method $method = Method::DELETE;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $listId,
        protected int $mergeId
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/lists/{$this->listId}/merge-fields/{$this->mergeId}";
    }
}
