<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Jobs\ReadImportFile;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ExportFixtures;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\Support\SpreadsheetFixtures;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    ProductType::factory()->create();
    $this->group = CollectionGroup::factory()->create();

    AiFakes::configure();
    ColumnMappingAgent::fake(fn (string $prompt) => ExportFixtures::productListAnswer(str_contains($prompt, '"sheet":"product-list-hu.csv"') ? 'hu' : 'ro'));
});

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Upload the product list export for an employee of the given language,
 * read and analyze it with the faked answer, and stage it.
 */
function stageProductList(string $locale): ProductImport
{
    $import = ImportBuilder::uploaded(["product-list-{$locale}.csv" => ExportFixtures::productList($locale)], [
        'options' => (new ImportOptions(collectionGroupId: test()->group->id))->toArray(),
    ]);

    ReadImportFile::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);

    app(ContinueToStaging::class)->handle($import->fresh());

    return $import->fresh();
}

function listRow(ProductImport $import, string $sku): ProductImportRow
{
    return ProductImportRow::includedIn($import)->get()->sole(fn (ProductImportRow $row): bool => $row->effectiveRow()->get('variant.sku') === $sku);
}

test('a tax-inclusive store reads the formatted price, and the image is the original of the thumbnail', function (string $locale) {
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    $import = stageProductList($locale);

    $shirt = listRow($import, 'CAM-12');
    $belt = listRow($import, 'CUR-14');

    expect($import->sheets()->sole()->sheetMapping()->column(1)->format->imageSuffix)->toBe('-small_default')
        ->and($shirt->effectiveRow()->get('variant.price'))->toBe(150385)
        ->and($shirt->effectiveRow()->get('product.images'))->toBe(['https://shop.example.com/img/p/1/2/12.jpg'])
        ->and($shirt->effectiveRow()->has('product.status'))->toBeFalse()
        ->and($shirt->role)->toBe(RowRole::ProductAndVariant)
        ->and($belt->effectiveRow()->get('variant.price'))->toBe(11900)
        ->and($belt->effectiveRow()->get('product.images'))->toBe(['https://shop.example.com/img/p/1/4/14.jpg']);
})->with(['ro', 'hu']);

test('a tax-exclusive store reads the raw price with six decimals', function () {
    config(['lunar.pricing.stored_inclusive_of_tax' => false]);

    $import = stageProductList('ro');

    expect(listRow($import, 'CAM-12')->effectiveRow()->get('variant.price'))->toBe(125659)
        ->and(listRow($import, 'CUR-14')->effectiveRow()->get('variant.price'))->toBe(10000)
        ->and($import->sheets()->sole()->sheetMapping()->column(6)->target)->toBe('ignore');
});

test('an escaped negative stock reads -3 and is an error', function () {
    $import = stageProductList('ro');

    $belt = listRow($import, 'CUR-14');

    expect($belt->effectiveRow()->get('variant.stock'))->toBe(-3)
        ->and($belt->status)->toBe(RowStatus::Error)
        ->and(collect($belt->messages)->pluck('message')->all())->toContain('The stock "-3" cannot be negative')
        ->and(listRow($import, 'CAM-12')->effectiveRow()->get('variant.stock'))->toBe(10);
});
