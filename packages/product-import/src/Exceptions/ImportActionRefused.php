<?php

namespace Lunar\ProductImport\Exceptions;

use RuntimeException;

class ImportActionRefused extends RuntimeException
{
    /**
     * Refuse with a translated message from the package's `errors` group.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $key, array $replace = []): self
    {
        return new self(__('lunarpanel.product-import::errors.'.$key, $replace));
    }
}
