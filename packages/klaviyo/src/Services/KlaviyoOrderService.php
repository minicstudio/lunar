<?php

namespace Lunar\Klaviyo\Services;

use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Order;

class KlaviyoOrderService
{
    public function __construct(protected KlaviyoProfileService $profileService) {}

    /**
     * Sync a placed order as a Klaviyo "Placed Order" event.
     *
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function syncPlacedOrder(Order $order): array
    {
        $order->loadMissing(['user', 'billingAddress', 'currency', 'productLines.purchasable.product']);

        $email = $this->resolveOrderEmail($order);

        if (! $email) {
            KlaviyoLogger::error('Order sync missing email', [
                'order_id' => $order->id,
            ]);

            throw new FailedKlaviyoSyncException(
                "Order {$order->id} has no email for Klaviyo sync (user or billing contact_email required)."
            );
        }

        $lines = $order->productLines->map(function ($line) {
            $unitPrice = $line->quantity > 0 ? ($line->total->value / $line->quantity) / 100 : 0;

            return [
                'ProductId' => (string) ($line->purchasable->product->id ?? ''),
                'ProductName' => $line->purchasable->product?->translateAttribute('name') ?? $line->description,
                'SKU' => $line->purchasable->sku ?? '',
                'Quantity' => $line->quantity,
                'ItemPrice' => $unitPrice,
                'RowTotal' => $line->total->decimal(),
            ];
        })->values()->all();

        $value = (float) $order->total->decimal();
        $currency = $order->currency->code;

        KlaviyoLogger::debug('Syncing placed order to Klaviyo', [
            'order_id' => $order->id,
            'email' => $email,
            'line_count' => count($lines),
            'value' => $value,
            'currency' => $currency,
            'metric' => (string) config('lunar.klaviyo.placed_order_metric', 'Placed Order'),
        ]);

        return $this->profileService->trackEvent(
            email: $email,
            eventName: (string) config('lunar.klaviyo.placed_order_metric', 'Placed Order'),
            properties: [
                'OrderId' => (string) $order->id,
                'Items' => $lines,
                'ItemNames' => collect($lines)->pluck('ProductName')->filter()->values()->all(),
            ],
            eventId: (string) $order->id,
            value: $value,
            valueCurrency: $currency,
        );
    }

    protected function resolveOrderEmail(Order $order): ?string
    {
        if ($order->user_id && $order->user?->email) {
            return $order->user->email;
        }

        return $order->billingAddress?->contact_email;
    }
}
