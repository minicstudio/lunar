<?php

use Illuminate\Support\Facades\Config;
use Lunar\Admin\Support\Orders\OrderInvoicePdf;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Tests\Admin\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->currency = Currency::factory()->create(['default' => true]);
    Language::factory()->create(['default' => true]);

    $this->order = Order::factory()->create(['currency_code' => $this->currency->code]);

    $this->taxBreakdown = function (array $percentagesToTax): TaxBreakdown {
        $taxBreakdown = new TaxBreakdown;

        foreach ($percentagesToTax as $percentage => $tax) {
            $taxBreakdown->addAmount(new TaxBreakdownAmount(
                price: new Price($tax, $this->currency, 1),
                identifier: "vat-{$percentage}",
                description: "VAT {$percentage}%",
                percentage: $percentage,
            ));
        }

        return $taxBreakdown;
    };

    $this->createLine = fn (array $attributes): OrderLine => OrderLine::factory()->create([
        'order_id' => $this->order->id,
        'option' => null,
        'unit_quantity' => 1,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'meta' => null,
        ...$attributes,
    ]);

    $this->shipping = fn (int $price): ShippingBreakdown => new ShippingBreakdown(collect([
        new ShippingBreakdownItem(name: 'DPD', identifier: 'dpd', price: new Price($price, $this->currency, 1)),
    ]));

    $this->rowsOf = fn (OrderInvoicePdf $invoice): array => $invoice->rows()->map(fn (array $row) => [
        $row['name'],
        $row['details'],
        $row['unit'],
        $row['quantity'],
        $row['unitPrice']->value,
        $row['value']->value,
        $row['vat']->value,
    ])->all();
});

it('lists products before the coupon, shipping and a negative coupon line with VAT', function () {
    // 30% coupon on 30.00 (1 piece) and 100.00 (1 pack of 4), shipping 15.00, 21% VAT, prices excluding tax.
    $pieceLine = ($this->createLine)([
        'description' => 'Gucci',
        'option' => 'UK 8, Green',
        'quantity' => 1,
        'sub_total' => 3000,
        'discount_total' => 900,
        'tax_breakdown' => ($this->taxBreakdown)([21 => 441]),
        'tax_total' => 441,
        'total' => 2541,
    ]);
    $packLine = ($this->createLine)([
        'description' => 'Quantity test',
        'unit_quantity' => 4,
        'quantity' => 4,
        'sub_total' => 10000,
        'discount_total' => 3000,
        'tax_breakdown' => ($this->taxBreakdown)([21 => 1470]),
        'tax_total' => 1470,
        'total' => 8470,
    ]);

    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'COUPON30',
        'data' => ['fixed_value' => false, 'percentage' => 30],
    ]);
    $this->order->update([
        'tax_breakdown' => ($this->taxBreakdown)([21 => 2226]),
        'shipping_breakdown' => ($this->shipping)(1500),
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect([
                    (object) ['line' => $pieceLine, 'quantity' => 1, 'amount' => new Price(900, $this->currency, 1)],
                    (object) ['line' => $packLine, 'quantity' => 4, 'amount' => new Price(3000, $this->currency, 1)],
                ]),
                'total' => new Price(3900, $this->currency, 1),
            ],
        ]),
    ]);

    $invoice = new OrderInvoicePdf($this->order->fresh());

    expect(($this->rowsOf)($invoice))->toBe([
        ['Gucci - UK 8, Green', null, 'buc', 1, 3000, 3000, 630],
        ['Quantity test', '1 pachet = 4 buc', 'pachet', 1, 10000, 10000, 2100],
        ['Cost de livrare', null, 'buc', 1, 1500, 1500, 315],
        ['Cupon de reducere - COUPON30', null, 'buc', 1, -3900, -3900, -819],
    ])
        ->and($invoice->showsVat())->toBeTrue()
        ->and($invoice->vatPercentage())->toBe(21.0)
        ->and($invoice->netTotal()->value)->toBe(10600)
        ->and($invoice->vatTotal()->value)->toBe(2226)
        ->and($invoice->total()->value)->toBe(12826);
});

it('derives amounts excluding VAT when prices are stored including tax', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', true);

    ($this->createLine)([
        'description' => 'Quantity test',
        'unit_quantity' => 4,
        'quantity' => 4,
        'sub_total' => 10000,
        'tax_breakdown' => ($this->taxBreakdown)([21 => 1736]),
        'tax_total' => 1736,
        'total' => 10000,
    ]);
    ($this->createLine)([
        'description' => 'Test',
        'quantity' => 2,
        'sub_total' => 10000,
        'tax_breakdown' => ($this->taxBreakdown)([21 => 1736]),
        'tax_total' => 1736,
        'total' => 10000,
    ]);
    $this->order->update([
        'tax_breakdown' => ($this->taxBreakdown)([21 => 3732]),
        'shipping_breakdown' => ($this->shipping)(1500),
    ]);

    $invoice = new OrderInvoicePdf($this->order->fresh());

    expect(($this->rowsOf)($invoice))->toBe([
        ['Quantity test', '1 pachet = 4 buc', 'pachet', 1, 8264, 8264, 1736],
        ['Test', null, 'buc', 2, 4132, 8264, 1736],
        ['Cost de livrare', null, 'buc', 1, 1240, 1240, 260],
    ])
        ->and($invoice->netTotal()->value)->toBe(17768)
        ->and($invoice->vatTotal()->value)->toBe(3732)
        ->and($invoice->total()->value)->toBe(21500);
});

it('lists redeemed loyalty points as a negative line with VAT', function () {
    // 100.00 product, 10.00 loyalty redeemed before tax, 21% VAT, prices excluding tax.
    ($this->createLine)([
        'description' => 'Test',
        'quantity' => 1,
        'sub_total' => 10000,
        'discount_total' => 1000,
        'tax_breakdown' => ($this->taxBreakdown)([21 => 1890]),
        'tax_total' => 1890,
        'total' => 10890,
    ]);
    $this->order->update([
        'tax_breakdown' => ($this->taxBreakdown)([21 => 1890]),
        'meta' => ['loyalty_points_to_redeem' => 1000, 'loyalty_discount_minor' => 1000],
    ]);

    $invoice = new OrderInvoicePdf($this->order->fresh());

    expect(($this->rowsOf)($invoice))->toBe([
        ['Test', null, 'buc', 1, 10000, 10000, 2100],
        ['Reducere puncte de fidelitate', null, 'buc', 1, -1000, -1000, -210],
    ])
        ->and($invoice->total()->value)->toBe(10890);
});

it('shows plain prices without VAT when the order has no tax rate above 0%', function (array $percentagesToTax) {
    ($this->createLine)([
        'description' => 'Used shirt',
        'quantity' => 2,
        'sub_total' => 5000,
        'total' => 5000,
    ]);
    $this->order->update([
        'tax_breakdown' => ($this->taxBreakdown)($percentagesToTax),
        'shipping_breakdown' => ($this->shipping)(1500),
    ]);

    $invoice = new OrderInvoicePdf($this->order->fresh());

    expect($invoice->showsVat())->toBeFalse()
        ->and(($this->rowsOf)($invoice))->toBe([
            ['Used shirt', null, 'buc', 2, 2500, 5000, 0],
            ['Cost de livrare', null, 'buc', 1, 1500, 1500, 0],
        ])
        ->and($invoice->total()->value)->toBe(6500);
})->with([
    'no tax rates' => [[]],
    'zero rate' => [[0 => 0]],
]);
