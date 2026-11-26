<?php

namespace Lunar\Analytics\Conversions\Services;

use Carbon\CarbonInterface;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\Cart;
use Throwable;

class ConversionEventRecorder
{
    /**
     * The traffic recorder is used only for `shouldTrack()`, which does not
     * depend on the traffic switch.
     */
    public function __construct(protected TrafficVisitRecorder $trafficVisitRecorder) {}

    /**
     * Whether conversion recording is switched on (`lunar.analytics.conversions.enabled`, off by default).
     */
    public function enabled(): bool
    {
        return (bool) config('lunar.analytics.conversions.enabled', false);
    }

    /**
     * Record an add to cart, and the cart's start when it has none yet.
     */
    public function addToCart(Cart $cart): void
    {
        if (! $this->shouldRecord($cart)) {
            return;
        }

        try {
            $now = now();

            if (! $this->hasEvent($cart, ConversionEventType::CartStarted)) {
                $this->record($cart, ConversionEventType::CartStarted, $now);
            }

            $this->record($cart, ConversionEventType::AddToCart, $now);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Record the cart's checkout start, unless it already has one.
     */
    public function beginCheckout(Cart $cart): void
    {
        if (! $this->shouldRecord($cart)) {
            return;
        }

        try {
            if ($this->hasEvent($cart, ConversionEventType::BeginCheckout)) {
                return;
            }

            $this->record($cart, ConversionEventType::BeginCheckout, now());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Whether recording is on, the request may be tracked and the cart is saved.
     */
    protected function shouldRecord(Cart $cart): bool
    {
        return $this->enabled()
            && $this->trafficVisitRecorder->shouldTrack()
            && $cart->exists;
    }

    /**
     * Whether the cart already has an event of the given type.
     */
    protected function hasEvent(Cart $cart, ConversionEventType $type): bool
    {
        return ConversionEvent::query()
            ->where('cart_id', $cart->getKey())
            ->where('type', $type)
            ->exists();
    }

    protected function record(Cart $cart, ConversionEventType $type, CarbonInterface $at): void
    {
        ConversionEvent::query()->create([
            'type' => $type,
            'cart_id' => $cart->getKey(),
            'created_at' => $at,
        ]);
    }
}
