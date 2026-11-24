<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\InvalidApiKeyException;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Actions\RetrySheetAnalysis;
use Lunar\ProductImport\Actions\SkipSheet;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Jobs\StageImport;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    ProductType::factory()->create();
    AiFakes::configure();
    Queue::fake([StageImport::class]);

    $this->import = ImportBuilder::import(ImportStatus::Analyzing);
    $this->produse = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Denumire', 'Pret'],
        ['TB-1-S', 'Tricou', '49,90'],
    ], 1, ['analysis_status' => SheetAnalysisStatus::Pending]);
    $this->outlet = ImportBuilder::sheet($this->import, 'Outlet', [
        ['Cod', 'Denumire'],
        ['OUT-1', 'Geacă'],
    ], 1, ['analysis_status' => SheetAnalysisStatus::Pending]);
});

function sheetAnswer(): array
{
    return AiFakes::answer([[0, 'variant.sku'], [1, 'product.name', 0.95, 'ro']]);
}

test('continuing is refused while a sheet failed and allowed after a retry', function () {
    $failProduse = true;

    ColumnMappingAgent::fake(function (string $prompt) use (&$failProduse) {
        if ($failProduse && str_contains($prompt, '"sheet":"Produse"')) {
            throw new InvalidApiKeyException('openai', new Response(new Psr7Response(401)));
        }

        return sheetAnswer();
    });

    app(AnalysisCoordinator::class)->startForImport($this->import);

    expect($this->import->fresh()->status)->toBe(ImportStatus::AnalysisFailed);

    expect(fn () => app(ContinueToStaging::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'The sheet "Produse" has no AI proposal or applied profile yet.');

    $failProduse = false;
    app(RetrySheetAnalysis::class)->handle($this->produse->fresh());

    app(ContinueToStaging::class)->handle($this->import->fresh());

    expect($this->import->fresh()->status)->toBe(ImportStatus::Staging);
    Queue::assertPushed(StageImport::class, fn (StageImport $job) => $job->importId === $this->import->id);
});

test('skipping the failed sheet allows continuing', function () {
    ColumnMappingAgent::fake(function (string $prompt) {
        if (str_contains($prompt, '"sheet":"Produse"')) {
            throw new InvalidApiKeyException('openai', new Response(new Psr7Response(401)));
        }

        return sheetAnswer();
    });

    app(AnalysisCoordinator::class)->startForImport($this->import);
    app(SkipSheet::class)->handle($this->produse->fresh());

    app(ContinueToStaging::class)->handle($this->import->fresh());

    expect($this->import->fresh()->status)->toBe(ImportStatus::Staging);
});

test('an invalid mapping blocks continuing', function () {
    ColumnMappingAgent::fake([sheetAnswer(), sheetAnswer()]);
    app(AnalysisCoordinator::class)->startForImport($this->import);

    $mapping = $this->outlet->fresh()->mapping;
    $mapping['product_type_id'] = null;
    $this->outlet->fresh()->forceFill(['mapping' => $mapping])->save();

    expect(fn () => app(ContinueToStaging::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'Sheet "Outlet": Choose a product type.');

    expect($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});

test('the status column option without a mapped status column blocks continuing', function () {
    ColumnMappingAgent::fake([sheetAnswer(), sheetAnswer()]);
    app(AnalysisCoordinator::class)->startForImport($this->import);

    $this->import->fresh()->forceFill(['options' => (new ImportOptions(newProductStatus: NewProductStatus::FromColumn))->toArray()])->save();

    expect(fn () => app(ContinueToStaging::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'New products take their status from the status column, but no included sheet maps a status column.');

    $mapping = $this->outlet->fresh()->sheetMapping();
    $mapping->columns[] = new ColumnMapping(2, 'Activ', 'product.status');
    $this->outlet->fresh()->forceFill(['mapping' => $mapping->toArray()])->save();

    app(ContinueToStaging::class)->handle($this->import->fresh());

    expect($this->import->fresh()->status)->toBe(ImportStatus::Staging);
});

test('a pending analysis without a configured provider blocks continuing', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    config(['lunar.product-import.ai.provider' => null]);

    expect(fn () => app(ContinueToStaging::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'AI analysis is not configured. The provider or API key is missing. Contact your developer.');
});
