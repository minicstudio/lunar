<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Actions\ReturnToMapping;
use Lunar\ProductImport\Actions\ReturnToValueReview;
use Lunar\ProductImport\Actions\SaveSheetMapping;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    AiFakes::configure();
    ColumnMappingAgent::fake();

    $this->productType = ProductType::factory()->create();
    $this->import = ImportBuilder::import(ImportStatus::AwaitingMapping);

    // 40 one-variant products; row 37 has no price; "Greutate" is not mapped yet.
    $this->sheet = ImportBuilder::sheet($this->import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret', 'Greutate'],
        40,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", $i + 1 === 37 ? '' : '49,90', '0,5'],
    ));

    ImportBuilder::map($this->sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $this->productType->id);
});

function row37(): ProductImportRow
{
    return ProductImportRow::query()->where('row_number', 37)->firstOrFail();
}

test('a mapping without category, brand, or option columns ends in preview ready without the ai or the file', function () {
    expect(Storage::disk('local')->exists(ImportBuilder::filePath($this->import)))->toBeFalse();

    app(ContinueToStaging::class)->handle($this->import);

    $this->import->refresh();

    ColumnMappingAgent::assertNeverPrompted();

    expect($this->import->status)->toBe(ImportStatus::PreviewReady)
        ->and(ProductImportRow::where('status', RowStatus::Valid)->count())->toBe(39)
        ->and(row37()->status)->toBe(RowStatus::Error)
        ->and(row37()->effectiveRow()->get('variant.price'))->toBeNull()
        ->and(ProductImportRow::where('row_number', 2)->first()->effectiveRow()->get('variant.price'))->toBe(4990);
});

test('going back, adding a weight column, and continuing stages weights and keeps the correction', function () {
    app(ContinueToStaging::class)->handle($this->import);

    row37()->update(['overrides' => ['variant.price' => 4990]]);

    app(ReturnToMapping::class)->handle($this->import->fresh());

    expect($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);

    $mapping = $this->sheet->fresh()->sheetMapping();
    $mapping->column(3)->target = 'variant.weight';
    $mapping->column(3)->format = ColumnFormat::number(',', '.');
    $mapping->weightUnit = 'kg';
    app(SaveSheetMapping::class)->handle($this->sheet->fresh(), $mapping);

    app(ContinueToStaging::class)->handle($this->import->fresh());

    $row = row37();

    ColumnMappingAgent::assertNeverPrompted();

    expect($this->import->fresh()->status)->toBe(ImportStatus::PreviewReady)
        ->and($row->overrides)->toBe(['variant.price' => 4990])
        ->and(NormalizedRow::fromArray($row->normalized)->get('variant.price'))->toBeNull()
        ->and($row->effectiveRow()->get('variant.price'))->toBe(4990)
        ->and($row->effectiveRow()->get('variant.weight'))->toBe(0.5)
        ->and($row->status)->toBe(RowStatus::Valid)
        ->and($row->raw[2] ?? null)->toBeNull();
});

test('staging leaves the catalog unchanged', function () {
    app(ContinueToStaging::class)->handle($this->import);

    expect(Product::count())->toBe(0)
        ->and(ProductVariant::count())->toBe(0);
});

test('returning to the value review needs values to review', function () {
    app(ContinueToStaging::class)->handle($this->import);

    expect(fn () => app(ReturnToValueReview::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'This import has no categories, brands, or option values to review.');
});

test('returning to the mapping is refused while staging', function () {
    $this->import->update(['status' => ImportStatus::Staging]);

    expect(fn () => app(ReturnToMapping::class)->handle($this->import))
        ->toThrow(ImportActionRefused::class, 'The import is being processed. Wait until it has finished.');
});
