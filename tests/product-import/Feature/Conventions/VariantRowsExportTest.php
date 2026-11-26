<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Jobs\ReadImportFile;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Structuring\RowStructurer;
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
    ColumnMappingAgent::fake(fn () => ExportFixtures::variantRowsAnswer());
});

afterEach(fn () => SpreadsheetFixtures::cleanup());

function stageVariantRows(): ProductImport
{
    $import = ImportBuilder::uploaded(['variant-rows.csv' => ExportFixtures::variantRows()], [
        'options' => (new ImportOptions(collectionGroupId: test()->group->id))->toArray(),
    ]);

    ReadImportFile::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);

    app(ContinueToStaging::class)->handle($import->fresh());

    return $import->fresh();
}

test('one row per variant is grouped by product, with packed options and the base price plus the impact', function () {
    $import = stageVariantRows();

    $rows = ProductImportRow::includedIn($import)->orderBy('row_number')->get();

    expect($rows->pluck('group_key')->unique())->toHaveCount(1)
        ->and($rows->map(fn (ProductImportRow $row) => $row->effectiveRow()->get('variant.price'))->all())->toBe([5000, 5000, 5500])
        ->and($rows->map(fn (ProductImportRow $row) => $row->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->all())->toBe([
            [['name' => 'Size', 'value' => 'S']],
            [['name' => 'Size', 'value' => 'M']],
            [['name' => 'Size', 'value' => 'L']],
        ])
        ->and($rows->pluck('status')->unique()->all())->toBe([RowStatus::Valid]);
});

test('product 7 gets three variants at 50.00, 50.00, and 55.00', function () {
    $import = stageVariantRows();

    ImportBuilder::commit($import);

    $product = Product::query()->sole();
    $prices = $product->variants()->orderBy('sku')->get()
        ->mapWithKeys(fn ($variant): array => [$variant->sku => $variant->basePrices()->sole()->price->value])
        ->all();

    expect($prices)->toBe(['TR-7-L' => 5500, 'TR-7-M' => 5000, 'TR-7-S' => 5000])
        ->and($product->variants()->where('sku', 'TR-7-L')->sole()->values()->count())->toBe(1);
});
