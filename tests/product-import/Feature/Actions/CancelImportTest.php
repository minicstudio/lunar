<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\CancelImport;
use Lunar\ProductImport\Actions\DecideValue;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Grouping\ProductGrouper;
use Lunar\ProductImport\Jobs\StageImport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\RowNormalizer;
use Lunar\ProductImport\Staging\ImportNormalizer;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $this->productType = ProductType::factory()->create();
});

test('a cancel during value review gives cancelled', function () {
    $import = ImportBuilder::import(ImportStatus::ReviewingValues);

    app(CancelImport::class)->handle($import);

    expect($import->fresh()->status)->toBe(ImportStatus::Cancelled)
        ->and($import->fresh()->status->isCancellable())->toBeFalse()
        ->and($import->fresh()->status->allowedTransitions())->toBe([]);
});

test('a cancel while importing is refused', function () {
    $import = ImportBuilder::import(ImportStatus::Importing);

    expect(fn () => app(CancelImport::class)->handle($import))
        ->toThrow(ImportActionRefused::class, 'This import can no longer be cancelled.');

    expect($import->fresh()->status)->toBe(ImportStatus::Importing);
});

test('a cancelled staging stops without changing rows further', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(['Cod', 'Denumire', 'Pret'], 1200, fn (int $i) => ["SKU-{$i}", "Produs {$i}", '10']));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $this->productType->id);

    // Staff cancel while the first chunk is being normalized.
    app()->bind(ImportNormalizer::class, fn () => new class(app(RowNormalizer::class), app(ProductGrouper::class), app(TargetFieldRegistry::class)) extends ImportNormalizer
    {
        public function normalize(ProductImport $import, ?callable $shouldContinue = null): bool
        {
            $calls = 0;

            return parent::normalize($import, function () use ($import, $shouldContinue, &$calls): bool {
                if (++$calls === 2) {
                    app(CancelImport::class)->handle($import->fresh());
                }

                return $shouldContinue === null || $shouldContinue();
            });
        }
    });

    StageImport::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::Cancelled)
        ->and(ProductImportRow::whereNotNull('normalized')->count())->toBe(500)
        ->and(ProductImportRow::where('status', '!=', RowStatus::Pending)->count())->toBe(0)
        ->and($import->values()->count())->toBe(0);
});

test('an import cancelled after a create category decision leaves the catalog unchanged', function () {
    $group = CollectionGroup::factory()->create();
    Collection::factory()->create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new Text('Femei')])]);

    $import = ImportBuilder::import(ImportStatus::Staging, ['options' => (new ImportOptions(collectionGroupId: $group->id))->toArray()]);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Denumire', 'Pret', 'Categorie'], ['A-1', 'Produs', '10', 'Accesorii']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.category'], $this->productType->id);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::ReviewingValues]);

    $collections = Collection::count();

    app(DecideValue::class)->create($import->values()->sole(), 'Accesorii noi');
    app(CancelImport::class)->handle($import->fresh());

    expect($import->fresh()->status)->toBe(ImportStatus::Cancelled)
        ->and(Collection::count())->toBe($collections);
});
