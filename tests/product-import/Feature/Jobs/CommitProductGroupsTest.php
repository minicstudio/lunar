<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\CommitContext;
use Lunar\ProductImport\Persisting\FieldValues;
use Lunar\ProductImport\Persisting\ProductUpserter;
use Lunar\ProductImport\Persisting\ProductUrls;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('one failing group is rolled back and marked failed while the other 33 are imported', function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $productType = ProductType::factory()->create();

    // A database error while writing the variant of "BOOM".
    app()->bind(ProductUpserter::class, fn () => new class(app(FieldValues::class), app(ProductUrls::class)) extends ProductUpserter
    {
        protected function writeVariant(Product $product, ?ProductVariant $variant, NormalizedRow $row, SheetMapping $mapping, array $optionIds, CommitContext $context): ProductVariant
        {
            if ($row->get('variant.sku') === 'BOOM') {
                throw new RuntimeException('Deadlock found when trying to get lock');
            }

            return parent::writeVariant($product, $variant, $row, $mapping, $optionIds, $context);
        }
    });

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        34,
        fn (int $i) => [$i === 17 ? 'BOOM' : "SKU-{$i}", $i === 17 ? 'Produs exploziv' : "Produs {$i}", '10'],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $productType->id);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $failed = ProductImportRow::query()->where('status', RowStatus::Failed)->sole();

    expect(Product::count())->toBe(33)
        ->and(ProductVariant::where('sku', 'BOOM')->exists())->toBeFalse()
        ->and($failed->match_value)->toBe('BOOM')
        ->and(collect($failed->messages)->last()['message'])->toBe('Writing this product failed: Deadlock found when trying to get lock')
        ->and(ProductImportRow::where('status', RowStatus::Imported)->count())->toBe(33);

    expect(Product::all()->contains(fn (Product $product) => $product->translateAttribute('name', 'ro') === 'Produs exploziv'))->toBeFalse();
});
