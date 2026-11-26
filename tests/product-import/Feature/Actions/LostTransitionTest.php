<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\ContinueToPreview;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Actions\RetryFailedRows;
use Lunar\ProductImport\Actions\ReturnToMapping;
use Lunar\ProductImport\Actions\ReturnToValueReview;
use Lunar\ProductImport\Actions\StartImport;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();

    Bus::fake();
});

/**
 * A staged one-product import, then set to the given status.
 */
function lostTransitionImport(int $productTypeId, ImportStatus $status): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Denumire', 'Pret'], ['TB-1', 'Tricou', '49,90']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $productTypeId);
    ImportBuilder::stage($import);
    $import->update(['status' => $status]);

    return $import->refresh();
}

/**
 * Let the action's conditional status update lose: right after its guard
 * reads the stored status, another request moves the import to `$to`.
 */
function loseNextTransition(ProductImport $import, ImportStatus $to): void
{
    $moved = false;

    ProductImport::retrieved(function (ProductImport $model) use ($import, $to, &$moved) {
        if ($moved || $model->getKey() !== $import->getKey()) {
            return;
        }

        $moved = true;

        ProductImport::query()->whereKey($import->getKey())->update(['status' => $to->value]);
    });
}

const ACTION_NOT_COMPLETED = 'The action could not be completed. Reload the page and try again.';

test('continuing to staging after another request moved the import gives the general message', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::AwaitingMapping);

    expect(app(ContinueToStaging::class)->blocker($import))->toBeNull();

    loseNextTransition($import, ImportStatus::Staging);

    expect(fn () => app(ContinueToStaging::class)->handle($import))
        ->toThrow(ImportActionRefused::class, ACTION_NOT_COMPLETED);

    Bus::assertNothingDispatched();
});

test('continuing to the preview after another request moved the import gives the general message', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::ReviewingValues);

    loseNextTransition($import, ImportStatus::AwaitingMapping);

    expect(fn () => app(ContinueToPreview::class)->handle($import))
        ->toThrow(ImportActionRefused::class, ACTION_NOT_COMPLETED)
        ->and($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);

    Bus::assertNothingDispatched();
});

test('returning to mapping after another request moved the import gives the general message', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::PreviewReady);

    loseNextTransition($import, ImportStatus::Cancelled);

    expect(fn () => app(ReturnToMapping::class)->handle($import))
        ->toThrow(ImportActionRefused::class, ACTION_NOT_COMPLETED);

    Bus::assertNothingDispatched();
});

test('returning to the value review after another request moved the import gives the general message', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::PreviewReady);
    ProductImportValue::factory()->for($import, 'import')->create(['action' => ValueAction::Create]);

    loseNextTransition($import, ImportStatus::AwaitingMapping);

    expect(fn () => app(ReturnToValueReview::class)->handle($import))
        ->toThrow(ImportActionRefused::class, ACTION_NOT_COMPLETED);

    Bus::assertNothingDispatched();
});

test('starting the import after another request moved it gives the general message and dispatches no job', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::PreviewReady);

    loseNextTransition($import, ImportStatus::Cancelled);

    expect(fn () => app(StartImport::class)->handle($import))
        ->toThrow(ImportActionRefused::class, ACTION_NOT_COMPLETED)
        ->and($import->fresh()->status)->toBe(ImportStatus::Cancelled);

    Bus::assertNothingDispatched();
    Bus::assertNothingChained();
    Bus::assertNothingBatched();
});

test('retrying failed rows after another request moved the import gives the general message and dispatches no job', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::CompletedWithErrors);
    $import->rows()->update(['status' => RowStatus::Failed->value]);

    loseNextTransition($import, ImportStatus::Importing);

    expect(fn () => app(RetryFailedRows::class)->handle($import))
        ->toThrow(ImportActionRefused::class, ACTION_NOT_COMPLETED);

    Bus::assertNothingDispatched();
    Bus::assertNothingChained();
    Bus::assertNothingBatched();
});

test('returning to mapping while the stored status is staging says the import is being processed', function () {
    $import = lostTransitionImport($this->productType->id, ImportStatus::Staging);

    expect(fn () => app(ReturnToMapping::class)->handle($import))
        ->toThrow(ImportActionRefused::class, 'The import is being processed. Wait until it has finished.');
});
