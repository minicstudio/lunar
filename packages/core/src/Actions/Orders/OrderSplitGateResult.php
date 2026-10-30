<?php

namespace Lunar\Actions\Orders;

/**
 * Failed split gates, plus a separate ERP warning that does not block the split.
 */
final class OrderSplitGateResult
{
    /**
     * @param  list<string>  $failed
     */
    public function __construct(
        public array $failed,
        public bool $erpWarning,
    ) {}

    public function passes(): bool
    {
        return $this->failed === [];
    }
}
