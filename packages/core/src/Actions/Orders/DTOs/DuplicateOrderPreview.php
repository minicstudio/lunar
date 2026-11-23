<?php

namespace Lunar\Actions\Orders\DTOs;

/**
 * Payment and shipping choices for the duplicate modal. Opening it stores nothing.
 */
final class DuplicateOrderPreview
{
    /**
     * @param  list<string>  $paymentOptions
     * @param  list<DuplicateOrderShippingMethod>  $shippingMethods
     */
    public function __construct(
        public array $paymentOptions,
        public ?string $paymentMethod,
        public array $shippingMethods,
        public ?string $shippingMethod,
    ) {}
}
