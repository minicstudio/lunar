<?php

namespace Lunar\Marketing\Concerns;

use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Exceptions\SilentException;

trait TracksStorefrontMarketingEvents
{
    /**
     * Dispatch a provider-neutral storefront marketing event with a stable eventId.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function dispatchStorefrontMarketingEvent(
        string $email,
        string $eventName,
        array $properties = [],
        ?string $uniqueKey = null,
    ): void {
        try {
            event(new StorefrontMarketingEventOccurred(
                email: $email,
                eventName: $eventName,
                properties: $properties,
                uniqueKey: $uniqueKey,
            ));
        } catch (\Exception $e) {
            report(new SilentException(
                "Failed to dispatch storefront marketing event '{$eventName}'. Error: ".$e->getMessage()
            ));
        }
    }

    /**
     * Track remove_from_cart with properties built once and a stable eventId.
     *
     * @param  int  $lineId  The ID of the cart line being removed.
     */
    protected function trackRemoveFromCartMarketingEvent(int $lineId): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $line = $this->cart->lines?->find($lineId);
        $productVariant = $line?->purchasable;

        if (! $productVariant) {
            return;
        }

        $prices = $productVariant->getPricesForDatalayerAndGTM();
        $occurredAt = now()->toIso8601String();

        $this->dispatchStorefrontMarketingEvent(
            email: $user->email,
            eventName: 'remove_from_cart',
            properties: [
                'product_id' => (string) $productVariant->product_id,
                'product_name' => $productVariant->product->translateAttribute('name'),
                'variant_id' => (string) $productVariant->id,
                'sku' => $productVariant->sku,
                'price' => (string) ($prices['sale'] ?? $prices['original']),
                'currency' => $prices['currency'],
                'quantity' => (string) ($line->quantity ?? 1),
            ],
            uniqueKey: "remove_from_cart:line:{$lineId}:{$occurredAt}",
        );
    }
}
