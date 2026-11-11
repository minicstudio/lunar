<?php

namespace Lunar\Analytics\Traffic\Observers;

use ArrayObject;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\Cart;

/**
 * Stamps a new cart with the source of the session's current visit.
 *
 * Only `creating` is handled, so the first source stays: later visits never
 * change it. Landing path and referrer host stay on the visit only.
 */
class StampCartWithTrafficSource
{
    public function __construct(protected TrafficVisitRecorder $recorder) {}

    /**
     * Merge `traffic_source` (`visit_id`, `source`, `medium`, `campaign`) into
     * the new cart's meta, keeping its other keys. Does not save the cart.
     */
    public function creating(Cart $cart): void
    {
        $visit = $this->recorder->current();

        if ($visit === null) {
            return;
        }

        $meta = $cart->meta instanceof ArrayObject ? $cart->meta->getArrayCopy() : (array) ($cart->meta ?? []);

        if (isset($meta['traffic_source'])) {
            return;
        }

        $meta['traffic_source'] = $visit;

        $cart->meta = $meta;
    }
}
