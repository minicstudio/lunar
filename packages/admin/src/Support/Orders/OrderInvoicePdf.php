<?php

namespace Lunar\Admin\Support\Orders;

use Illuminate\Support\Collection;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use NumberFormatter;

/**
 * Lines and totals for the order invoice PDF (`lunarpanel::pdf.order`).
 *
 * Built like the SmartBill invoice: product lines before the coupon and loyalty points, a shipping
 * line and negative coupon and loyalty lines, each with its value excluding VAT and its VAT.
 */
class OrderInvoicePdf
{
    /**
     * VAT rate of the order; 0 when the seller is not registered for VAT.
     */
    protected float $vatPercentage;

    /**
     * @var Collection<int, array{name: string, details: ?string, unit: string, quantity: int, unitPrice: Price, value: Price, vat: Price}>
     */
    protected Collection $rows;

    public function __construct(protected Order $order)
    {
        $this->vatPercentage = (float) collect($order->tax_breakdown?->amounts)->max('percentage');

        $this->rows = $order->productLines->toBase()->map(function (OrderLine $line): array {
            $inPacks = $line->isInvoicedInPacks();

            return $this->row(
                name: $line->description.($line->option ? ' - '.$line->option : ''),
                details: $line->packSize() > 1 ? "1 pachet = {$line->packSize()} buc" : null,
                unit: $inPacks ? 'pachet' : 'buc',
                quantity: $inPacks ? $line->invoicePackQuantity() : (int) $line->quantity,
                price: ($inPacks ? $line->invoicePackPrice() : $line->invoiceUnitPrice())->value,
                percentage: $line->tax_rate * 100,
            );
        });

        if ($order->shipping_sub_total->value > 0) {
            $shippingPercentage = (float) $order->shippingLines->first()?->tax_breakdown?->amounts?->first()?->percentage;

            $this->rows->push($this->row('Cost de livrare', null, 'buc', 1, $order->shipping_sub_total->value, $shippingPercentage ?: $this->vatPercentage));
        }

        if ($coupon = $order->appliedCoupon) {
            $this->rows->push($this->row(
                'Cupon de reducere'.($coupon->coupon ? ' - '.$coupon->coupon : ''),
                null,
                'buc',
                1,
                -abs($order->coupon_total_without_tax->value),
                $this->vatPercentage,
            ));
        }

        $loyaltyDiscount = (int) ($order->meta['loyalty_discount_minor'] ?? 0);

        if ($loyaltyDiscount > 0) {
            $this->rows->push($this->row('Reducere puncte de fidelitate', null, 'buc', 1, -$loyaltyDiscount, $this->vatPercentage));
        }
    }

    /**
     * @return Collection<int, array{name: string, details: ?string, unit: string, quantity: int, unitPrice: Price, value: Price, vat: Price}>
     */
    public function rows(): Collection
    {
        return $this->rows;
    }

    /**
     * Whether the VAT column and totals are shown (not for sellers without a tax rate above 0%).
     */
    public function showsVat(): bool
    {
        return $this->vatPercentage > 0;
    }

    public function vatPercentage(): float
    {
        return $this->vatPercentage;
    }

    /**
     * Sum of the line values excluding VAT.
     */
    public function netTotal(): Price
    {
        return $this->price($this->rows->sum(fn (array $row) => $row['value']->value));
    }

    public function vatTotal(): Price
    {
        return $this->price($this->rows->sum(fn (array $row) => $row['vat']->value));
    }

    public function total(): Price
    {
        return $this->price($this->netTotal()->value + $this->vatTotal()->value);
    }

    /**
     * An amount without the currency, which is shown once in the column headers.
     */
    public function amount(Price $price): string
    {
        return $price->formatted(null, NumberFormatter::DECIMAL);
    }

    /**
     * One invoice line from a unit price as stored (including VAT when prices are stored inclusive of tax).
     *
     * @return array{name: string, details: ?string, unit: string, quantity: int, unitPrice: Price, value: Price, vat: Price}
     */
    protected function row(string $name, ?string $details, string $unit, int $quantity, int $price, float $percentage): array
    {
        $inclusive = (bool) config('lunar.pricing.stored_inclusive_of_tax', false);
        $rate = $percentage / 100;
        $value = $price * $quantity;
        $netValue = $inclusive ? (int) round($value / (1 + $rate)) : $value;

        return [
            'name' => $name,
            'details' => $details,
            'unit' => $unit,
            'quantity' => $quantity,
            'unitPrice' => $this->price($inclusive ? $price / (1 + $rate) : $price),
            'value' => $this->price($netValue),
            'vat' => $this->price($inclusive ? $value - $netValue : $netValue * $rate),
        ];
    }

    protected function price(int|float $value): Price
    {
        return new Price((int) round($value), $this->order->currency);
    }
}
