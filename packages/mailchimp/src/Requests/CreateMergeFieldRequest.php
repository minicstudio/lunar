<?php

namespace Lunar\Mailchimp\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateMergeFieldRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected string $listId,
        protected array $data
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return "/lists/{$this->listId}/merge-fields";
    }

    /**
     * The request body.
     */
    protected function defaultBody(): array
    {
        return $this->data;
    }
}
