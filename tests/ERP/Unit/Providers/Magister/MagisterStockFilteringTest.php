<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\Models\ErpSyncTemp;
use Lunar\ERP\Providers\Magister\Jobs\CreateProductsAndVariantsJob;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpImporter;

beforeEach(function () {
    Config::set('lunar.erp.magister.base_url', 'https://magister.test');
    Config::set('lunar.erp.magister.app_id', 'APP');
    Config::set('lunar.erp.magister.shop_id', 1);

    // Clean up temp table
    ErpSyncTemp::truncate();
});

it('only processes articles with stock greater than 0', function () {
    Bus::fake();

    // Mock articles data - some with stock, some without
    $articlesDataset = [
        [
            'IDSMARTCASH' => 100,
            'SALECODE' => 'SKU-100',
            'NAME' => 'Product with Stock',
            'PRICE' => 10.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 0,
            'RECVERSION' => 1,
        ],
        [
            'IDSMARTCASH' => 200,
            'SALECODE' => 'SKU-200',
            'NAME' => 'Product without Stock',
            'PRICE' => 20.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 0,
            'RECVERSION' => 1,
        ],
    ];

    $mockApi = \Mockery::mock(MagisterApiClient::class);

    // Mock product sync calls
    $mockApi->shouldReceive('getProductList')
        ->once()
        ->andReturn(['result' => [['DATASET' => $articlesDataset]]]);

    $mockApi->shouldReceive('getProductList')
        ->once()
        ->andReturn(['result' => [['DATASET' => []]]]);

    $mockApi->shouldReceive('confirmReceivingData')
        ->once()
        ->withArgs([101, 1])
        ->andReturn(['ok' => true]);

    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(100)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 5]]]]]);

    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(200)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 0]]]]]);

    $importer = new MagisterErpImporter($mockApi);
    $result = $importer->syncProducts();

    expect($result['success'])->toBeTrue();

    // Only the product with stock should trigger a job
    Bus::assertDispatched(CreateProductsAndVariantsJob::class, 1);

    expect(ErpSyncTemp::count())->toBe(1);
    expect(ErpSyncTemp::where('erp_id', 100)->exists())->toBeTrue();
});

it('includes generic products when their variants have stock', function () {
    Bus::fake();

    // Mock a generic product with variants
    $articlesDataset = [
        [
            'IDSMARTCASH' => 300,
            'SALECODE' => 'GEN-300',
            'NAME' => 'Generic Product',
            'PRICE' => 0.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 1, // Generic
            'RECVERSION' => 1,
        ],
        [
            'IDSMARTCASH' => 301,
            'SALECODE' => 'VAR-301',
            'NAME' => 'Variant Red',
            'PRICE' => 15.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 2, // Variant
            'IDSMARTCASH_GENERIC_ARTICLE' => 300,
            'RECVERSION' => 1,
        ],
        [
            'IDSMARTCASH' => 302,
            'SALECODE' => 'VAR-302',
            'NAME' => 'Variant Blue',
            'PRICE' => 15.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 2, // Variant
            'IDSMARTCASH_GENERIC_ARTICLE' => 300,
            'RECVERSION' => 1,
        ],
    ];

    $mockApi = \Mockery::mock(MagisterApiClient::class);

    // Mock product sync calls
    $mockApi->shouldReceive('getProductList')
        ->once()
        ->andReturn(['result' => [['DATASET' => $articlesDataset]]]);

    $mockApi->shouldReceive('getProductList')
        ->once()
        ->andReturn(['result' => [['DATASET' => []]]]);

    $mockApi->shouldReceive('confirmReceivingData')
        ->once()
        ->withArgs([101, 1])
        ->andReturn(['ok' => true]);

    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(300)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 1]]]]]);

    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(301)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 3]]]]]);

    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(302)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 0]]]]]);

    $importer = new MagisterErpImporter($mockApi);
    $result = $importer->syncProducts();

    expect($result['success'])->toBeTrue();

    Bus::assertDispatched(CreateProductsAndVariantsJob::class);

    expect(ErpSyncTemp::count())->toBe(2);
    expect(ErpSyncTemp::where('erp_id', 300)->exists())->toBeTrue();
    expect(ErpSyncTemp::where('erp_id', 301)->exists())->toBeTrue();
});

it('excludes generic products when no variants have stock', function () {
    Bus::fake();

    // Mock a generic product with variants that have no stock
    $articlesDataset = [
        [
            'IDSMARTCASH' => 400,
            'SALECODE' => 'GEN-400',
            'NAME' => 'Generic Product No Stock',
            'PRICE' => 0.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 1, // Generic
            'RECVERSION' => 1,
        ],
        [
            'IDSMARTCASH' => 401,
            'SALECODE' => 'VAR-401',
            'NAME' => 'Variant Red No Stock',
            'PRICE' => 15.00,
            'CATEG_1' => 'Cat1',
            'CATEG_2' => 'Cat2',
            'ARTICLE_KIND' => 2, // Variant
            'IDSMARTCASH_GENERIC_ARTICLE' => 400,
            'RECVERSION' => 1,
        ],
    ];

    $mockApi = \Mockery::mock(MagisterApiClient::class);

    // Mock product sync calls
    $mockApi->shouldReceive('getProductList')
        ->once()
        ->andReturn(['result' => [['DATASET' => $articlesDataset]]]);

    $mockApi->shouldReceive('getProductList')
        ->once()
        ->andReturn(['result' => [['DATASET' => []]]]);

    $mockApi->shouldReceive('confirmReceivingData')
        ->once()
        ->withArgs([101, 1])
        ->andReturn(['ok' => true]);

    // Mock stock sync calls
    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(400)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 0]]]]]);

    $mockApi->shouldReceive('getArticleStockByShop')
        ->once()
        ->with(401)
        ->andReturn(['result' => [['DATASET' => [['NRSHOP' => 98, 'STOCK' => 0]]]]]);

    $importer = new MagisterErpImporter($mockApi);
    $result = $importer->syncProducts();

    expect($result['success'])->toBeTrue();

    // No jobs should be dispatched since no products have stock
    Bus::assertNotDispatched(CreateProductsAndVariantsJob::class);

    // Check that no items remain in temp table
    expect(ErpSyncTemp::count())->toBe(0);
});
