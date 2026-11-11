<?php

use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Tests\Admin\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->currency = Currency::factory()->create(['default' => true]);
    $country = Country::factory()->create();
    Language::factory()->create(['default' => true]);

    $this->order = Order::factory()
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->create([
            'currency_code' => $this->currency->code,
            'shipping_breakdown' => new ShippingBreakdown(collect([
                new ShippingBreakdownItem(name: 'DPD', identifier: 'dpd', price: new Price(1500, $this->currency, 1)),
            ])),
        ]);

    $this->normalize = fn (string $text): string => trim(preg_replace('/\s+/', ' ', $text));

    $this->renderPdfText = fn (): string => ($this->normalize)(strip_tags(
        view('lunarpanel::pdf.order', ['record' => $this->order->fresh()])->render()
    ));

    $this->money = fn (int $value): string => (new Price($value, $this->currency))->formatted(null, NumberFormatter::DECIMAL);

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

    $this->vat21 = function (int $tax): TaxBreakdown {
        $taxBreakdown = new TaxBreakdown;
        $taxBreakdown->addAmount(new TaxBreakdownAmount(
            price: new Price($tax, $this->currency, 1),
            identifier: 'vat',
            description: 'VAT',
            percentage: 21,
        ));

        return $taxBreakdown;
    };
});

it('lists lines with prices excluding VAT and the VAT of each line', function () {
    ($this->createLine)([
        'description' => 'Gucci',
        'option' => 'UK 8, Green',
        'quantity' => 1,
        'sub_total' => 3000,
        'tax_breakdown' => ($this->vat21)(630),
        'tax_total' => 630,
        'total' => 3630,
    ]);
    ($this->createLine)([
        'description' => 'Quantity test',
        'unit_quantity' => 4,
        'quantity' => 4,
        'sub_total' => 10000,
        'tax_breakdown' => ($this->vat21)(2100),
        'tax_total' => 2100,
        'total' => 12100,
    ]);
    $this->order->update(['tax_breakdown' => ($this->vat21)(3045)]);

    $money = $this->money;
    $code = $this->currency->code;

    expect(($this->renderPdfText)())
        ->toContain('Cota TVA: 21%')
        ->toContain("# Denumire U.M. Cant. Preț fără TVA {$code} Valoare {$code} Valoare TVA {$code}")
        ->toContain('1 Gucci - UK 8, Green buc 1 '.$money(3000).' '.$money(3000).' '.$money(630))
        ->toContain('2 Quantity test 1 pachet = 4 buc pachet 1 '.$money(10000).' '.$money(10000).' '.$money(2100))
        ->toContain('3 Cost de livrare buc 1 '.$money(1500).' '.$money(1500).' '.$money(315))
        ->toContain('Total '.$money(14500).' '.$money(3045))
        ->toContain('Total factură '.$money(17545));
});

it('shows plain prices and no VAT when the order carries no tax', function () {
    ($this->createLine)([
        'description' => 'Used shirt',
        'quantity' => 2,
        'sub_total' => 5000,
        'total' => 5000,
    ]);

    $money = $this->money;
    $code = $this->currency->code;
    $text = ($this->renderPdfText)();

    expect($text)
        ->toContain("# Denumire U.M. Cant. Preț unitar {$code} Valoare {$code}")
        ->toContain('1 Used shirt buc 2 '.$money(2500).' '.$money(5000))
        ->toContain('2 Cost de livrare buc 1 '.$money(1500).' '.$money(1500))
        ->toContain('Total factură '.$money(6500))
        ->not->toContain('TVA');
});
