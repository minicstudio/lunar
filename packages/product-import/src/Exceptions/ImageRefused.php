<?php

namespace Lunar\ProductImport\Exceptions;

use RuntimeException;

/**
 * An image that is not fetched or not kept, with the reason staff see.
 */
class ImageRefused extends RuntimeException
{
    /**
     * Refuse with a translated reason from `images.reasons`.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $reason, array $replace = []): self
    {
        return new self(__('lunarpanel.product-import::images.reasons.'.$reason, $replace));
    }
}
