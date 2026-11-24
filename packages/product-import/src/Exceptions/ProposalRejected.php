<?php

namespace Lunar\ProductImport\Exceptions;

use RuntimeException;

class ProposalRejected extends RuntimeException
{
    public const INVALID_RESPONSE = 'invalid_response';

    public const NO_PRODUCT_DATA = 'no_product_data';

    public function __construct(public readonly string $failureCode, public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /**
     * The answer breaks the structure or the allowed lists.
     */
    public static function invalid(string $reason): self
    {
        return new self(self::INVALID_RESPONSE, $reason);
    }

    /**
     * The answer maps no product name and no SKU.
     */
    public static function noProductData(): self
    {
        return new self(self::NO_PRODUCT_DATA, __('lunarpanel.product-import::analysis.reasons.no_product_data'));
    }
}
