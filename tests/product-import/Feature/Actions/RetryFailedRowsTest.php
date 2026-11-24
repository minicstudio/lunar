<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\CorrectRow;
use Lunar\ProductImport\Actions\RetryFailedRows;
use Lunar\ProductImport\Actions\StartImport;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Exports\ErrorReport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\CommitContext;
use Lunar\ProductImport\Persisting\FieldValues;
use Lunar\ProductImport\Persisting\ProductUpserter;
use Lunar\ProductImport\Persisting\ProductUrls;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $productType = ProductType::factory()->create();

    // The ERP rejects prices below 1.00, so those products fail while writing.
    $this->written = new ArrayObject;
    $written = $this->written;

    app()->bind(ProductUpserter::class, fn () => new class(app(FieldValues::class), app(ProductUrls::class), $written) extends ProductUpserter
    {
        public function __construct(FieldValues $fields, ProductUrls $urls, protected ArrayObject $written)
        {
            parent::__construct($fields, $urls);
        }

        public function upsert(ProductImport $import, string $groupKey, CommitContext $context): void
        {
            $this->written[] = $groupKey;

            $price = $this->importableRows($import, $groupKey)->first()?->effectiveRow()->get('variant.price');

            if ($price !== null && $price < 100) {
                throw new RuntimeException('Price below the minimum');
            }

            parent::upsert($import, $groupKey, $context);
        }
    });

    $this->import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($this->import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        20,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", $i <= 3 ? '0,50' : '49,90'],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $productType->id);
    ImportBuilder::stage($this->import);
    $this->import->update(['status' => ImportStatus::PreviewReady]);

    app(StartImport::class)->handle($this->import);
    $this->written->exchangeArray([]);
});

function failedRow(string $sku): ProductImportRow
{
    return ProductImportRow::query()->where('match_value', $sku)->firstOrFail();
}

test('correcting 2 failed rows and retrying writes only the failed groups and lowers the failed count by 2', function () {
    expect($this->import->fresh()->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($this->import->fresh()->stats['failed_rows'])->toBe(3);

    app(CorrectRow::class)->handle(failedRow('SKU-1'), ['variant.price' => '49,90']);
    app(CorrectRow::class)->handle(failedRow('SKU-2'), ['variant.price' => '59,90']);

    expect(failedRow('SKU-1')->status)->toBe(RowStatus::Failed);

    app(RetryFailedRows::class)->handle($this->import->fresh());

    $import = $this->import->fresh();

    expect($this->written->getArrayCopy())->toHaveCount(3)
        ->and(collect($this->written->getArrayCopy())->every(fn (string $key) => in_array($key, [
            failedRow('SKU-1')->group_key, failedRow('SKU-2')->group_key, failedRow('SKU-3')->group_key,
        ], true)))->toBeTrue()
        ->and(failedRow('SKU-1')->status)->toBe(RowStatus::Imported)
        ->and(failedRow('SKU-2')->status)->toBe(RowStatus::Imported)
        ->and(failedRow('SKU-3')->status)->toBe(RowStatus::Failed)
        ->and(ProductVariant::where('sku', 'SKU-2')->first()->basePrices->first()->price->value)->toBe(5990)
        ->and($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($import->stats['failed_rows'])->toBe(1)
        ->and($import->stats['imported_rows'])->toBe(19);
});

test('a pruned import refuses the retry and the error report', function () {
    $this->import->update(['pruned_at' => now()]);

    expect(fn () => app(RetryFailedRows::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'The row data of this import was removed.');

    expect(fn () => app(ErrorReport::class)->lines($this->import->fresh())->current())
        ->not->toThrow(Throwable::class);

    expect(fn () => app(ErrorReport::class)->write($this->import->fresh(), fopen('php://memory', 'w')))
        ->toThrow(ImportActionRefused::class, 'The row data of this import was removed.');
});
