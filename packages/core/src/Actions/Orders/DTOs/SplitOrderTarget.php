<?php

namespace Lunar\Actions\Orders\DTOs;

/**
 * One new order in a split.
 *
 * `shippingMinor` is null when the preview should start from the calculated price.
 */
final class SplitOrderTarget
{
    /**
     * @param  array<int, int>  $quantities  Order line id => quantity assigned to this target.
     */
    public function __construct(
        public string $key,
        public array $quantities,
        public ?int $shippingMinor,
        public string $paymentOption,
        public ?string $paymentType = null,
    ) {}
}
