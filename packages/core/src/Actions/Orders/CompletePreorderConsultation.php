<?php

namespace Lunar\Actions\Orders;

use Illuminate\Support\Facades\Schema;
use Lunar\Actions\AbstractAction;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\Taxes;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\PreorderConsultationShipping;
use Throwable;

/**
 * Record a finished preorder phone consultation and move the order on.
 *
 * Shipping is stored first, then the consultation timestamp, then the payment
 * method and status together. The status save is what the existing payment
 * emails read. This action does not create an AWB or send the order to the ERP.
 */
final class CompletePreorderConsultation extends AbstractAction
{
    /**
     * Payment methods the consultation form and a split opened from it can set.
     *
     * @var list<string>
     */
    public const PAYMENT_OPTIONS = [
        'bt-ipay',
        'stripe-card',
        'bank-transfer',
        'cash-on-delivery',
    ];

    /**
     * @var list<string>
     */
    private const CARD_OPTIONS = [
        'bt-ipay',
        'stripe-card',
    ];

    public static function isStoreCollection(Order $order): bool
    {
        if (data_get($order->meta, 'shippingType') === 'pickup') {
            return true;
        }

        if (! class_exists(ShippingMethod::class)) {
            return false;
        }

        $table = (new ShippingMethod)->getTable();

        if (! Schema::hasTable($table)) {
            return false;
        }

        $identifier = $order->shippingLines()->value('identifier');

        if (! filled($identifier)) {
            return false;
        }

        return ShippingMethod::query()
            ->where('code', $identifier)
            ->value('driver') === 'collection';
    }

    /**
     * Minor units for the shipping field. Store collection is not quoted.
     */
    public static function suggestedShippingMinor(Order $order): int
    {
        if (self::isStoreCollection($order)) {
            return 0;
        }

        if (class_exists(PreorderConsultationShipping::class)) {
            $quoted = PreorderConsultationShipping::suggestedMinor($order);

            if ($quoted !== null) {
                return $quoted;
            }
        }

        return (int) ($order->shipping_total->value ?? 0);
    }

    public function execute(OrderContract $order, string $paymentOption, ?int $shippingMinor): Order
    {
        /** @var Order $order */
        if (! in_array($paymentOption, self::PAYMENT_OPTIONS, true)) {
            throw new \InvalidArgumentException('Unknown preorder consultation payment method.');
        }

        if ($shippingMinor !== null && $shippingMinor < 0) {
            throw new \InvalidArgumentException('The consultation shipping price cannot be negative.');
        }

        if (self::isStoreCollection($order)) {
            if ((int) $order->shipping_total->value !== 0) {
                $this->storeShipping($order, 0);
                $order->refresh();
            }
        } elseif ($shippingMinor !== null) {
            $this->storeShipping($order, $shippingMinor);
            $order->refresh();
        }

        $this->stampConsultation($order);
        $order->unsetRelation('lines');
        $order->unsetRelation('productLines');

        $meta = $this->metaArray($order->meta);
        $meta['payment_option'] = $paymentOption;
        $order->meta = $meta;
        $order->status = self::statusFor($paymentOption);
        $order->save();

        return $order->refresh();
    }

    /**
     * Status stored for a consultation payment method.
     *
     * Card becomes awaiting payment. Bank transfer becomes awaiting bank transfer.
     * Cash on delivery becomes payment offline.
     */
    public static function statusFor(string $paymentOption): string
    {
        if (in_array($paymentOption, self::CARD_OPTIONS, true)) {
            return 'awaiting-payment';
        }

        if ($paymentOption === 'bank-transfer') {
            return 'awaiting-bank-transfer';
        }

        return 'payment-offline';
    }

    private function storeShipping(Order $order, int $minor): void
    {
        $line = $order->shippingLines()->first() ?? new OrderLine([
            'order_id' => $order->id,
            'type' => 'shipping',
            'description' => 'Shipping',
            'identifier' => 'shipping',
            'unit_quantity' => 1,
            'quantity' => 1,
            'discount_total' => 0,
        ]);

        $oldShipping = (int) ($order->shipping_total->value ?? 0);
        $oldTax = (int) ($line->exists ? $line->tax_total->value : 0);
        $tax = $this->shippingTax($order, $line, $minor);
        $taxTotal = (int) $tax->amounts->sum('price.value');
        $lineTotal = prices_inc_tax() ? $minor : $minor + $taxTotal;

        $meta = $this->metaArray($line->meta);
        unset($meta['preorder_shipping_pending']);

        $line->fill([
            'order_id' => $order->id,
            'type' => 'shipping',
            'purchasable_type' => null,
            'purchasable_id' => null,
            'description' => $line->description ?: 'Shipping',
            'identifier' => $line->identifier ?: 'shipping',
            'unit_price' => $minor,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => $minor,
            'discount_total' => 0,
            'tax_breakdown' => $tax,
            'tax_total' => $taxTotal,
            'total' => $lineTotal,
            'meta' => $meta,
        ])->save();

        $order->update([
            'shipping_total' => $lineTotal,
            'tax_total' => max(0, (int) ($order->tax_total->value ?? 0) - $oldTax + $taxTotal),
            'total' => (int) ($order->total->value ?? 0) - $oldShipping + $lineTotal,
            'shipping_breakdown' => $this->shippingBreakdown($order, $line, $minor),
        ]);
    }

    private function shippingTax(Order $order, OrderLine $line, int $minor): TaxBreakdown
    {
        $taxClass = $this->shippingTaxClass($line);
        $currency = $order->currency;

        if (! $taxClass || ! $currency || ! TaxZone::getDefault()) {
            return new TaxBreakdown;
        }

        try {
            $option = new ShippingOption(
                name: $line->description ?: 'Shipping',
                description: null,
                identifier: $line->identifier ?: 'shipping',
                price: new Price($minor, $currency, 1),
                taxClass: $taxClass,
            );

            return Taxes::setShippingAddress($order->shippingAddress)
                ->setCurrency($currency)
                ->setPurchasable($option)
                ->getBreakdown($minor);
        } catch (Throwable) {
            return new TaxBreakdown;
        }
    }

    /**
     * Tax class recorded on the original shipping line, when one can be matched.
     */
    private function shippingTaxClass(OrderLine $line): ?TaxClass
    {
        foreach ($line->tax_breakdown?->amounts ?? [] as $amount) {
            if (! preg_match('/^tax_rate_(\d+)$/', (string) $amount->identifier, $matches)) {
                continue;
            }

            $matched = TaxRateAmount::query()
                ->where('tax_rate_id', (int) $matches[1])
                ->whereNotNull('tax_class_id')
                ->orderBy('id')
                ->get()
                ->first(function (TaxRateAmount $rateAmount) use ($amount): bool {
                    return abs((float) $rateAmount->percentage - (float) $amount->percentage) < 0.001;
                });

            if ($matched?->taxClass) {
                return $matched->taxClass;
            }
        }

        return TaxClass::getDefault();
    }

    private function shippingBreakdown(Order $order, OrderLine $line, int $minor): ShippingBreakdown
    {
        $currency = $order->currency ?? Currency::getDefault();
        $items = $order->shipping_breakdown?->items ?? collect();
        $identifier = $line->identifier ?: 'shipping';

        if ($currency) {
            $existing = $items->get($identifier);

            $items->put($identifier, new ShippingBreakdownItem(
                name: $existing?->name ?? ($line->description ?: 'Shipping'),
                identifier: $identifier,
                price: new Price($minor, $currency, 1),
            ));
        }

        return new ShippingBreakdown($items);
    }

    private function stampConsultation(Order $order): void
    {
        $completedAt = now()->toIso8601String();
        $order->load('productLines');

        foreach ($order->productLines as $line) {
            $meta = $this->metaArray($line->meta);

            if (($meta['preorder'] ?? null) !== true) {
                continue;
            }

            $meta['preorder_consultation_completed_at'] = $completedAt;
            $line->meta = $meta;
            $line->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function metaArray(mixed $meta): array
    {
        if ($meta instanceof \ArrayObject) {
            return $meta->getArrayCopy();
        }

        return is_array($meta) ? $meta : [];
    }
}
