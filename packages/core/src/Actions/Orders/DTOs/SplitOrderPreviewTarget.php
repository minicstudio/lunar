<?php

namespace Lunar\Actions\Orders\DTOs;

/**
 * Calculated amounts for one target. Prices are minor units.
 */
final class SplitOrderPreviewTarget
{
    /**
     * @param  list<array{order_line_id: int, description: string, quantity: int, unit_price: int, sub_total: int, discount: int, coupon_discount: int, coupon_code: ?string, total: int}>  $lines
     */
    public function __construct(
        public string $key,
        public int $shippingMinor,
        public bool $shippingPricingUnavailable,
        public int $subTotal,
        public int $discountTotal,
        public int $shippingTotal,
        public int $taxTotal,
        public int $total,
        public array $lines,
        public int $loyaltyPoints = 0,
    ) {}
}
