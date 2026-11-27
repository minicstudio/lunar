<?php

namespace Lunar\Analytics\Traffic\Observers;

use ArrayObject;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\CartLine;

/**
 * Stamps a cart with the source of the session's current visit when a
 * product line is added to it, so an empty cart, such as the one created
 * after a completed order, gets no source.
 *
 * A cart that has a source keeps it: later visits never change it. Landing
 * path and referrer host stay on the visit only.
 */
class StampCartWithTrafficSource
{
    public function __construct(protected TrafficVisitRecorder $recorder) {}

    /**
     * Merge `traffic_source` (`visit_id`, `source`, `medium`, `campaign`) into
     * the meta of the new line's cart, keeping its other keys, and save the
     * cart without events. Does nothing when the cart already has a source.
     */
    public function created(CartLine $line): void
    {
        $visit = $this->recorder->current();

        if ($visit === null) {
            return;
        }

        $cart = $line->cart;

        if ($cart === null) {
            return;
        }

        $meta = $cart->meta instanceof ArrayObject ? $cart->meta->getArrayCopy() : (array) ($cart->meta ?? []);

        if (isset($meta['traffic_source'])) {
            return;
        }

        $meta['traffic_source'] = $visit;

        $cart->meta = $meta;
        $cart->saveQuietly();
    }
}
