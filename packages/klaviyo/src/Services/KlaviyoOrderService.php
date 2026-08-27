<?php

namespace Lunar\Klaviyo\Services;

use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class KlaviyoOrderService
{
    public function __construct(
        protected KlaviyoProfileService $profileService,
        protected KlaviyoCatalogService $catalogService,
    ) {}

    /**
     * Sync a placed order as Klaviyo "Placed Order" + per-line "Ordered Product" events.
     *
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function syncPlacedOrder(Order $order): array
    {
        $order->loadMissing(['user', 'billingAddress', 'currency', 'productLines.purchasable.product.variants']);

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
            $purchasable = $line->purchasable;
            $product = $purchasable?->product;
            $productId = $this->resolveCatalogProductId($product, $purchasable);
            $variantId = $this->resolveCatalogVariantId($purchasable);

            return [
                'ProductID' => $productId,
                'VariantID' => $variantId,
                'ProductName' => $product?->translateAttribute('name') ?? $line->description,
                'SKU' => $purchasable->sku ?? '',
                'Quantity' => $line->quantity,
                'ItemPrice' => $unitPrice,
                'RowTotal' => $line->total->decimal(),
                'line_id' => (string) $line->id,
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

        $placedOrder = $this->profileService->trackEvent(
            email: $email,
            eventName: (string) config('lunar.klaviyo.placed_order_metric', 'Placed Order'),
            properties: [
                'OrderId' => (string) $order->id,
                'Items' => collect($lines)->map(fn (array $line) => collect($line)->except('line_id')->all())->values()->all(),
                'ItemNames' => collect($lines)->pluck('ProductName')->filter()->values()->all(),
            ],
            eventId: (string) $order->id,
            value: $value,
            valueCurrency: $currency,
        );

        $orderedProducts = [];
        $orderedProductMetric = (string) config('lunar.klaviyo.ordered_product_metric', 'Ordered Product');

        foreach ($lines as $line) {
            $orderedProducts[] = $this->profileService->trackEvent(
                email: $email,
                eventName: $orderedProductMetric,
                properties: [
                    'OrderId' => (string) $order->id,
                    'ProductID' => $line['ProductID'],
                    'VariantID' => $line['VariantID'],
                    'SKU' => $line['SKU'],
                    'ProductName' => $line['ProductName'],
                    'Quantity' => $line['Quantity'],
                    'ItemPrice' => $line['ItemPrice'],
                    'RowTotal' => $line['RowTotal'],
                ],
                eventId: 'order:'.$order->id.':line:'.$line['line_id'],
                value: (float) $line['RowTotal'],
                valueCurrency: $currency,
            );
        }

        return [
            'placed_order' => $placedOrder,
            'ordered_products' => $orderedProducts,
        ];
    }

    protected function resolveOrderEmail(Order $order): ?string
    {
        if ($order->user_id && $order->user?->email) {
            return $order->user->email;
        }

        return $order->billingAddress?->contact_email;
    }

    protected function resolveCatalogProductId(?Product $product, mixed $purchasable): string
    {
        if ($product) {
            return $this->catalogService->resolveItemExternalId($product);
        }

        if ($purchasable instanceof ProductVariant) {
            $sku = trim((string) ($purchasable->sku ?? ''));

            if ($sku !== '') {
                return str_replace('/', '-', $sku);
            }
        }

        return '';
    }

    protected function resolveCatalogVariantId(mixed $purchasable): string
    {
        if ($purchasable instanceof ProductVariant) {
            return (string) $purchasable->id;
        }

        return '';
    }
}
