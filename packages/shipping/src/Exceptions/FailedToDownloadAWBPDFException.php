<?php

namespace Lunar\Addons\Shipping\Exceptions;

use Exception;

class FailedToDownloadAWBPDFException extends Exception
{
    public function __construct(
        string $message,
        protected ?string $details = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }
}
