<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListMergeFieldsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $listId
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/lists/{$this->listId}/merge-fields";
    }
}
