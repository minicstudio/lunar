<?php

namespace Lunar\Actions\Orders;

/**
 * The assignment and choices for a split preview or confirm.
 */
final class SplitOrderRequest
{
    /**
     * @param  list<SplitOrderTarget>  $targets
     * @param  list<int>  $catalogPricedLineIds  Order lines priced from the catalog at the new quantity. Every other line keeps its stored unit price.
     */
    public function __construct(
        public array $targets,
        public ?string $couponTarget,
        public bool $applyCoupon,
        public ?string $loyaltyTarget,
        public bool $applyLoyalty,
        public array $catalogPricedLineIds = [],
    ) {}
}
