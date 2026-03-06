<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\Providers\Magister\Jobs\CreateProductsAndVariantsJob;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpImporter;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    Config::set('lunar.erp.magister.base_url', 'https://magister.test');
    Config::set('lunar.erp.magister.app_id', 'APP');
    Config::set('lunar.erp.magister.shop_id', 1);
});

it('syncProducts completes cycle when API returns empty dataset', function () {
    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getProductList')->once()->andReturn(['result' => [['DATASET' => []]]]);
    // No stock API call expected when dataset is empty (no temp data to process)

    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncProducts();
    expect($res['success'])->toBeTrue();
});

it('syncProducts stores articles, confirms receiving and dispatches jobs', function () {
    Bus::fake();

    $dataset = [
        [
            'IDSMARTCASH' => 123,
            'SALECODE' => 'SKU-123',
            'NAME' => 'Test Product',
            'PRICE' => 12.34,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 0,
            'RECVERSION' => 5,
        ],
    ];

    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getProductList')->once()->andReturn(['result' => [['DATASET' => $dataset]]]);
    $mockApi->shouldReceive('getProductList')->once()->andReturn(['result' => [['DATASET' => []]]]);
    $mockApi->shouldReceive('confirmReceivingData')->once()->withArgs([101, 5])->andReturn(['ok' => true]);

    // Mock stock sync call for the article stored in temp table
    $stockDataset = [
        [
            'NRSHOP' => 98,
            'STOCK' => 5,
        ],
    ];
    $mockApi->shouldReceive('getArticleStockByShop')->once()->with(123)->andReturn(['result' => [['DATASET' => $stockDataset]]]);

    $progressCalls = [];
    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncProducts(function ($done, $total, $msg) use (&$progressCalls) {
        $progressCalls[] = [$done, $total, $msg];
    });

    expect($res['success'])->toBeTrue();
    Bus::assertDispatched(CreateProductsAndVariantsJob::class);
});

it('syncOrderStatuses processes dataset and confirms receiving', function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();

    Order::factory()->create(['reference' => 'ORD-1', 'status' => 'awaiting-payment']);

    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getModifiedOrders')->once()->andReturn([
        'result' => [['DATASET' => [['ORDER_NUMBER' => 'ORD-1', 'STATUS' => 4, 'STATUS_SUBTYPE' => 1, 'RECVERSION' => 9]]]],
    ]);
    $mockApi->shouldReceive('confirmReceivingData')->once()->withArgs([2, 9])->andReturn(['ok' => true]);

    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncOrderStatuses();
    expect($res['success'])->toBeTrue();
});

it('syncOrderStatuses confirms receiving even when order not found', function () {
    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getModifiedOrders')->once()->andReturn([
        'result' => [['DATASET' => [['ORDER_NUMBER' => 'ORD-404', 'STATUS' => 1, 'STATUS_SUBTYPE' => 0, 'RECVERSION' => 11]]]],
    ]);
    $mockApi->shouldReceive('confirmReceivingData')->once()->withArgs([2, 11])->andReturn(['ok' => true]);

    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncOrderStatuses();
    expect($res['success'])->toBeTrue()
        ->and($res['orders_processed'])->toBe(0);
});

it('syncStock confirms receiving when dataset empty', function () {
    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getStock')->once()->andReturn(['result' => [['DATASET' => []]]]);

    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncStock();
    expect($res['success'])->toBeTrue();
});

it('syncStock updates variant stock and confirms receiving', function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();

    $variant = ProductVariant::factory()->create(['erp_id' => 777, 'stock' => 0]);

    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getStock')->once()->andReturn([
        'result' => [['DATASET' => [[
            'IDSMARTCASH' => 777,
            'STOCK' => 10,
            'RECVERSION' => 2,
        ]]]],
    ]);
    $mockApi->shouldReceive('getStock')->once()->andReturn(['result' => [['DATASET' => []]]]);
    $mockApi->shouldReceive('confirmReceivingData')->once()->withArgs([1, 2])->andReturn(['ok' => true]);

    $calls = 0;
    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncStock(function () use (&$calls) {
        $calls++;
    });

    expect($res['success'])->toBeTrue();
    expect($variant->refresh()->stock)->toBe(10);
    expect($calls)->toBeGreaterThanOrEqual(1);
});

it('syncStock skips items from NRSHOP=1', function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();

    $variant = ProductVariant::factory()->create(['erp_id' => 888, 'stock' => 3]);

    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getStock')->once()->andReturn([
        'result' => [['DATASET' => [[
            'IDSMARTCASH' => 888,
            'STOCK' => 99,
            'NRSHOP' => 1,
            'RECVERSION' => 7,
        ]]]],
    ]);
    $mockApi->shouldReceive('getStock')->once()->andReturn(['result' => [['DATASET' => []]]]);
    $mockApi->shouldReceive('confirmReceivingData')->once()->withArgs([1, 7])->andReturn(['ok' => true]);

    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncStock();

    expect($res['success'])->toBeTrue();
    expect($variant->refresh()->stock)->toBe(3);
});

it('syncStock confirms receiving and continues when variant not found', function () {
    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('getStock')->once()->andReturn([
        'result' => [['DATASET' => [[
            'IDSMARTCASH' => 9999,
            'STOCK' => 1,
            'RECVERSION' => 12,
        ]]]],
    ]);
    $mockApi->shouldReceive('getStock')->once()->andReturn(['result' => [['DATASET' => []]]]);
    $mockApi->shouldReceive('confirmReceivingData')->once()->withArgs([1, 12])->andReturn(['ok' => true]);

    $importer = new MagisterErpImporter($mockApi);
    $res = $importer->syncStock();
    expect($res['success'])->toBeTrue();
});
