<?php

namespace Lunar\Actions\Orders;

/**
 * Server-calculated preview. Opening it does not store a cart or an order.
 */
final class SplitOrderPreview
{
    /**
     * @param  list<SplitOrderPreviewTarget>  $targets
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $targets,
        public array $errors,
    ) {}

    public function canConfirm(): bool
    {
        return $this->errors === [];
    }
}
