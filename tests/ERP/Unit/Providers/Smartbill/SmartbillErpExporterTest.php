<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\ERP\Providers\Smartbill\Requests\DownloadInvoicePDFRequest;
use Lunar\ERP\Providers\Smartbill\Requests\GenerateInvoiceRequest;
use Lunar\ERP\Providers\Smartbill\SmartbillApiClient;
use Lunar\ERP\Providers\Smartbill\SmartbillErpExporter;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Tests\ERP\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('lunar.erp.smartbill.base_url', 'https://smartbill.test');
    Config::set('lunar.erp.smartbill.email', 'user@test');
    Config::set('lunar.erp.smartbill.token', 'tok');
    Config::set('lunar.erp.smartbill.company_vat_code', 'RO123');
    Config::set('lunar.erp.smartbill.series_name', 'S');
    Config::set('lunar.erp.smartbill.measuring_unit_name', 'buc');
    Config::set('lunar.erp.smartbill.save_to_db', false);
    Config::set('lunar.erp.smartbill.tax_names', [
        '0' => 'Fara TVA',
        '19' => 'TVA 19%',
        '21' => 'TVA 21%',
    ]);
    Config::set('lunar.erp.smartbill.observations', [
        'payment_map' => [
            'hosted-payment' => 'card',
            'offline' => 'ramburs',
        ],
    ]);

    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();

    $taxClass = TaxClass::factory()->create(['default' => true]);
    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    TaxRateAmount::factory()->create([
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
        'percentage' => 21,
    ]);
});

function makeOrderForSmartbillExporter(array $orderAttributes = []): Order
{
    $user = test()->createUser();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);

    $currency = Currency::where('default', true)->firstOrFail();
    $shippingBreakdown = new ShippingBreakdown(collect([
        new ShippingBreakdownItem(
            name: 'Shipping',
            identifier: 'dpd',
            price: new Price(0, $currency, 1),
        ),
    ]));

    $order = Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'contact_email' => 'billing@example.com',
            'line_one' => 'Billing Street 1',
            'city' => 'Cluj-Napoca',
            'tax_identifier' => '',
            'state' => 'Cluj',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+40700000000',
            'contact_email' => 'john@example.com',
            'line_one' => 'Street 1',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->create(array_merge([
            'reference' => '1525',
            'currency_code' => 'EUR',
            'meta' => [
                'payment_option' => 'cash-on-delivery',
            ],
            'shipping_breakdown' => $shippingBreakdown,
        ], $orderAttributes));

    return $order;
}

it('generateInvoice returns series & number and exporter maps order to payload', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 321];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    $resp = $exporter->generateInvoice($order);
    expect($resp)->toBe(['series' => 'S', 'number' => 321]);
    expect($captured['observations'] ?? null)->toBe('#1525_ramburs_dpd');
});

it('maps observations with empty payment slug when payment_type is missing from payment_map', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 9];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter([
        'meta' => ['payment_option' => 'Custom Driver!'],
    ]);

    $exporter->generateInvoice($order);
    expect($captured['observations'] ?? null)->toBe('#1525__dpd');
});

it('uses first shipping_breakdown item identifier for observations', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 10];
    });

    $currency = Currency::where('default', true)->firstOrFail();
    $order = makeOrderForSmartbillExporter();
    $order->shipping_breakdown = new ShippingBreakdown(collect([
        new ShippingBreakdownItem(
            name: 'Courier',
            identifier: 'RAW_BREAKDOWN_ID',
            price: new Price(500, $currency, 1),
        ),
    ]));
    $order->save();
    $order->refresh();

    $exporter = new SmartbillErpExporter($client);
    $exporter->generateInvoice($order);
    expect($captured['observations'] ?? null)->toBe('#1525_ramburs_RAW_BREAKDOWN_ID');
});

it('maps observations with empty shipping segment when shipping_breakdown has no items', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 11];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter([
        'shipping_breakdown' => new ShippingBreakdown(collect()),
    ]);

    $exporter->generateInvoice($order);
    expect($captured['observations'] ?? null)->toBe('#1525_ramburs_');
});

it('maps observations with empty reference segment when reference is blank', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 12];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter(['reference' => '   ']);

    $exporter->generateInvoice($order);
    expect($captured['observations'] ?? null)->toBe('#_ramburs_dpd');
});

it('generateInvoice throws FailedErpInvoiceGenerationException when Smartbill returns error', function () {
    $mock = new MockClient([
        GenerateInvoiceRequest::class => MockResponse::make(['errorText' => 'Invalid'], 200),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);
    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    expect(fn () => $exporter->generateInvoice($order))
        ->toThrow(FailedErpInvoiceGenerationException::class);
});

it('generateInvoice rethrows when client throws with a helpful message', function () {
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andThrow(new RuntimeException('failed'));

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    expect(fn () => $exporter->generateInvoice($order))
        ->toThrow(FailedErpInvoiceGenerationException::class, 'Invoice generation failed: failed');
});

it('downloadInvoicePDF proxies to client', function () {
    $mock = new MockClient([
        DownloadInvoicePDFRequest::class => MockResponse::make('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
    ]);
    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    $order = makeOrderForSmartbillExporter();
    $meta = $order->meta ?? [];
    $meta['billing_series'] = 'S';
    $meta['billing_number'] = 42;
    $order->meta = $meta;
    $order->save();

    $exporter = new SmartbillErpExporter($client);
    $resp = $exporter->downloadInvoicePDF($order);
    expect($resp->successful())->toBeTrue();
});

it('adds a shipping product when the order has shipping and maps client fields', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 1];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    $currency = Currency::where('default', true)->firstOrFail();
    $order->update([
        'shipping_breakdown' => new ShippingBreakdown(collect([
            new ShippingBreakdownItem(name: 'Shipping', identifier: 'dpd', price: new Price(1500, $currency, 1)),
        ])),
        'shipping_total' => 1815,
    ]);
    $order->refresh();

    $resp = $exporter->generateInvoice($order);
    expect($resp)->toBe(['series' => 'S', 'number' => 1]);

    $payload = $captured;
    expect($payload)->not->toBeNull();
    expect($payload['client']['vatCode'])->toBe('-');

    $shipping = collect($payload['products'])->firstWhere('code', 'SHIPPING');
    expect($shipping)->not->toBeNull();
    expect($shipping['name'])->toBe('Cost de livrare');
    expect($shipping['isService'])->toBeTrue();
    expect($shipping['measuringUnitName'])->toBe('buc');
});

it('sends shipping including tax when prices are stored including tax', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', true);

    $currency = Currency::where('code', 'EUR')->firstOrFail();
    $order = makeOrderForSmartbillExporter([
        'shipping_breakdown' => new ShippingBreakdown(collect([
            new ShippingBreakdownItem(name: 'Shipping', identifier: 'dpd', price: new Price(1815, $currency, 1)),
        ])),
        'shipping_total' => 1815,
        'tax_breakdown' => smartbillVat21(315),
    ]);

    expect(captureSmartbillProducts($order)['SHIPPING'])
        ->price->toBe(18.15)
        ->isTaxIncluded->toBeTrue();
});

it('does not add a shipping product when shipping is free', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 2];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    $order->update(['shipping_total' => 0]);
    $order->refresh();

    $resp = $exporter->generateInvoice($order);
    expect($resp)->toBe(['series' => 'S', 'number' => 2]);

    $payload = $captured;
    $shipping = collect($payload['products'])->firstWhere('code', 'SHIPPING');
    expect($shipping)->toBeNull();
});

it('sendOrder returns false by default for Smartbill exporter', function () {
    $client = new SmartbillApiClient;
    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();
    expect($exporter->sendOrder($order))->toBeFalse();
});

it('maps bank-transfer and stripe-wallet-pay payment options in observations', function (string $paymentOption, string $slug) {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 20];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter([
        'meta' => ['payment_option' => $paymentOption],
    ]);

    $exporter->generateInvoice($order);
    expect($captured['observations'] ?? null)->toBe("#1525_{$slug}_dpd");
})->with([
    ['bank-transfer', 'transfer'],
    ['stripe-wallet-pay', 'card'],
]);

it('sends complimentary product lines at price zero', function () {
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 21];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'type' => 'physical',
        'identifier' => 'GIFT-SKU',
        'quantity' => 1,
        'unit_price' => 0,
        'sub_total' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 0,
        'meta' => ['added_by_discount' => [1]],
    ]);

    $exporter->generateInvoice($order->fresh());

    $gift = collect($captured['products'])->firstWhere('code', 'GIFT-SKU');
    expect($gift)->not->toBeNull();
    expect($gift['price'])->toBe(0.0);
});

/**
 * Create a product line on the order with tax-free net totals.
 *
 * @param  array<string, mixed>  $attributes
 */
function createSmartbillPackTestLine(Order $order, array $attributes): OrderLine
{
    return OrderLine::factory()->create(array_merge([
        'order_id' => $order->id,
        'type' => 'physical',
        'unit_quantity' => 1,
        'discount_total' => 0,
        'tax_total' => 0,
        'meta' => null,
    ], $attributes));
}

/**
 * A 21% tax breakdown in the default currency.
 */
function smartbillVat21(int $tax): TaxBreakdown
{
    $taxBreakdown = new TaxBreakdown;
    $taxBreakdown->addAmount(new TaxBreakdownAmount(
        price: new Price($tax, Currency::where('default', true)->firstOrFail(), 1),
        identifier: 'vat',
        description: 'VAT',
        percentage: 21,
    ));

    return $taxBreakdown;
}

/**
 * Generate the invoice and return the captured SmartBill products keyed by code.
 *
 * @return Collection<string, array<string, mixed>>
 */
function captureSmartbillProducts(Order $order): Collection
{
    $captured = null;
    $client = Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 30];
    });

    (new SmartbillErpExporter($client))->generateInvoice($order->fresh());

    return collect($captured['products'])->keyBy('code');
}

it('invoices pack-priced lines in packs next to lines sold per piece', function () {
    $order = makeOrderForSmartbillExporter(['currency_code' => 'EUR']);

    createSmartbillPackTestLine($order, [
        'identifier' => 'PIECE-SKU',
        'quantity' => 3,
        'total' => 3000,
    ]);
    createSmartbillPackTestLine($order, [
        'identifier' => 'PACK-SKU',
        'unit_quantity' => 4,
        'quantity' => 8,
        'total' => 19998,
    ]);

    $products = captureSmartbillProducts($order);

    expect($products['PIECE-SKU'])
        ->measuringUnitName->toBe('buc')
        ->quantity->toBe(3.0)
        ->price->toBe(10.0)
        ->not->toHaveKey('productDescription');

    expect($products['PACK-SKU'])
        ->measuringUnitName->toBe('pachet')
        ->quantity->toBe(2.0)
        ->price->toBe(99.99)
        ->productDescription->toBe('1 pachet = 4 buc');
});

it('uses the configured pack measuring unit name', function () {
    Config::set('lunar.erp.smartbill.pack_measuring_unit_name', 'bax');

    $order = makeOrderForSmartbillExporter(['currency_code' => 'EUR']);

    createSmartbillPackTestLine($order, [
        'identifier' => 'PACK-SKU',
        'unit_quantity' => 6,
        'quantity' => 6,
        'total' => 1200,
    ]);

    expect(captureSmartbillProducts($order)['PACK-SKU'])
        ->measuringUnitName->toBe('bax')
        ->quantity->toBe(1.0)
        ->price->toBe(12.0)
        ->productDescription->toBe('1 bax = 6 buc');
});

it('keeps pack-priced lines in pieces when pack invoicing is disabled', function () {
    Config::set('lunar.erp.smartbill.invoice_packs', false);

    $order = makeOrderForSmartbillExporter(['currency_code' => 'EUR']);

    createSmartbillPackTestLine($order, [
        'identifier' => 'PACK-SKU',
        'unit_quantity' => 4,
        'quantity' => 8,
        'total' => 20000,
    ]);

    expect(captureSmartbillProducts($order)['PACK-SKU'])
        ->measuringUnitName->toBe('buc')
        ->quantity->toBe(8.0)
        ->price->toBe(25.0)
        ->productDescription->toBe('1 pachet = 4 buc');
});

it('invoices a pack line in pieces when its quantity is not a whole number of packs', function () {
    $order = makeOrderForSmartbillExporter(['currency_code' => 'EUR']);

    createSmartbillPackTestLine($order, [
        'identifier' => 'PACK-SKU',
        'unit_quantity' => 4,
        'quantity' => 6,
        'total' => 1500,
    ]);

    expect(captureSmartbillProducts($order)['PACK-SKU'])
        ->measuringUnitName->toBe('buc')
        ->quantity->toBe(6.0)
        ->price->toBe(2.5)
        ->productDescription->toBe('1 pachet = 4 buc');
});

it('sends complimentary pack lines as packs at price zero', function () {
    $order = makeOrderForSmartbillExporter(['currency_code' => 'EUR']);

    createSmartbillPackTestLine($order, [
        'identifier' => 'GIFT-PACK-SKU',
        'unit_quantity' => 4,
        'quantity' => 4,
        'total' => 0,
        'meta' => ['added_by_discount' => [1]],
    ]);

    expect(captureSmartbillProducts($order)['GIFT-PACK-SKU'])
        ->measuringUnitName->toBe('pachet')
        ->quantity->toBe(1.0)
        ->price->toBe(0.0);
});

it('sends shipping and coupon excluding tax when prices are stored excluding tax', function () {
    $currency = Currency::where('code', 'EUR')->firstOrFail();
    $order = makeOrderForSmartbillExporter([
        'currency_code' => 'EUR',
        'shipping_breakdown' => new ShippingBreakdown(collect([
            new ShippingBreakdownItem(name: 'Shipping', identifier: 'dpd', price: new Price(1500, $currency, 1)),
        ])),
        'shipping_total' => 1815,
        'tax_breakdown' => smartbillVat21(2226),
    ]);

    // 30% coupon on 30.00 (1 piece) + 100.00 (1 pack of 4), 21% VAT.
    $pieceLine = createSmartbillPackTestLine($order, [
        'identifier' => 'PIECE-SKU',
        'quantity' => 1,
        'sub_total' => 3000,
        'discount_total' => 900,
        'tax_breakdown' => smartbillVat21(441),
        'tax_total' => 441,
        'total' => 2541,
    ]);
    $packLine = createSmartbillPackTestLine($order, [
        'identifier' => 'PACK-SKU',
        'unit_quantity' => 4,
        'quantity' => 4,
        'sub_total' => 10000,
        'discount_total' => 3000,
        'tax_breakdown' => smartbillVat21(1470),
        'tax_total' => 1470,
        'total' => 8470,
    ]);

    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'COUPON30',
        'data' => ['fixed_value' => false, 'percentage' => 30],
    ]);
    $order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect([
                    (object) ['line' => $pieceLine, 'quantity' => 1, 'amount' => new Price(900, $currency, 1)],
                    (object) ['line' => $packLine, 'quantity' => 4, 'amount' => new Price(3000, $currency, 1)],
                ]),
                'total' => new Price(3900, $currency, 1),
            ],
        ]),
    ]);

    $products = captureSmartbillProducts($order);

    expect($products['PIECE-SKU']['price'])->toBe(30.0)
        ->and($products['PACK-SKU']['price'])->toBe(100.0)
        ->and($products['SHIPPING']['price'])->toBe(15.0)
        ->and($products['CUPON']['price'])->toBe(-39.0)
        ->and($products->pluck('isTaxIncluded')->unique()->all())->toBe([false]);
});

it('sends redeemed loyalty points as a negative line with the VAT of the products', function () {
    // 100.00 product, 10.00 loyalty redeemed before tax, 21% VAT, prices excluding tax.
    $order = makeOrderForSmartbillExporter([
        'tax_breakdown' => smartbillVat21(1890),
        'meta' => ['payment_option' => 'cash-on-delivery', 'loyalty_points_to_redeem' => 1000, 'loyalty_discount_minor' => 1000],
    ]);

    createSmartbillPackTestLine($order, [
        'identifier' => 'PIECE-SKU',
        'quantity' => 1,
        'sub_total' => 10000,
        'discount_total' => 1000,
        'tax_breakdown' => smartbillVat21(1890),
        'tax_total' => 1890,
        'total' => 10890,
    ]);

    $products = captureSmartbillProducts($order);

    expect($products['PIECE-SKU']['price'])->toBe(100.0)
        ->and($products['LOIALITATE'])->toMatchArray([
            'name' => 'Reducere puncte de fidelitate',
            'quantity' => 1.0,
            'price' => -10.0,
            'isTaxIncluded' => false,
            'taxPercentage' => 21.0,
            'isService' => true,
        ]);
});

it('sends no VAT fields when the order has no tax rate above 0%', function () {
    // A seller not registered for VAT: no 0% tax name and no default tax zone configured.
    Config::set('lunar.erp.smartbill.tax_names', ['21' => 'Normala']);
    TaxZone::query()->update(['default' => false]);

    $currency = Currency::where('code', 'EUR')->firstOrFail();
    $order = makeOrderForSmartbillExporter([
        'shipping_breakdown' => new ShippingBreakdown(collect([
            new ShippingBreakdownItem(name: 'Shipping', identifier: 'dpd', price: new Price(1500, $currency, 1)),
        ])),
        'shipping_total' => 1500,
        'tax_total' => 0,
    ]);

    $line = createSmartbillPackTestLine($order, [
        'identifier' => 'USED-SHIRT',
        'quantity' => 1,
        'sub_total' => 3000,
        'discount_total' => 300,
        'total' => 2700,
    ]);

    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'COUPON10',
        'data' => ['fixed_value' => false, 'percentage' => 10],
    ]);
    $order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect([
                    (object) ['line' => $line, 'quantity' => 1, 'amount' => new Price(300, $currency, 1)],
                ]),
                'total' => new Price(300, $currency, 1),
            ],
        ]),
    ]);

    $products = captureSmartbillProducts($order);

    expect($products->map(fn (array $product) => $product['price'])->all())->toBe([
        'USED-SHIRT' => 30.0,
        'SHIPPING' => 15.0,
        'CUPON' => -3.0,
    ]);

    $products->each(fn (array $product) => expect($product)
        ->not->toHaveKey('isTaxIncluded')
        ->not->toHaveKey('taxName')
        ->not->toHaveKey('taxPercentage'));
});
