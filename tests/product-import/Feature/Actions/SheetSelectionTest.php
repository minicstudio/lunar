<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Language;
use Lunar\ProductImport\Actions\ChangeHeaderRow;
use Lunar\ProductImport\Actions\IncludeSheet;
use Lunar\ProductImport\Actions\SkipSheet;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->import = ImportBuilder::import(ImportStatus::AwaitingMapping);

    $this->produse = ImportBuilder::sheet($this->import, 'Produse', [
        ['Lista produse'],
        ['Cod', 'Denumire', 'Pret'],
        ['TB-1', 'Tricou', '49,90'],
        ['TB-2', 'Rochie', '129,00'],
    ], 1, ['mapping' => ['columns' => []], 'ai_proposal' => ['columns' => []]]);

    $this->outlet = ImportBuilder::sheet($this->import, 'Outlet', [
        ['Cod', 'Denumire'],
        ['OUT-1', 'Geacă'],
    ], 1, ['mapping' => ['columns' => [['index' => 0, 'target' => 'variant.sku']]]]);
});

test('a skipped sheet is excluded and can be included again', function () {
    app(SkipSheet::class)->handle($this->outlet);

    expect($this->outlet->fresh()->skipped)->toBeTrue()
        ->and($this->import->sheets()->where('skipped', false)->pluck('name')->all())->toBe(['Produse']);

    app(IncludeSheet::class)->handle($this->outlet->fresh());

    expect($this->outlet->fresh()->skipped)->toBeFalse()
        ->and($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});

test('skipping the failed sheet lets the import leave analysis failed', function () {
    $this->import->update(['status' => ImportStatus::AnalysisFailed]);
    $this->produse->update(['analysis_status' => SheetAnalysisStatus::Failed]);

    app(SkipSheet::class)->handle($this->produse);

    expect($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});

test('a header row change rebuilds only that sheet from the stored rows', function () {
    expect(Storage::disk('local')->exists(ImportBuilder::filePath($this->import)))->toBeFalse();

    $outletColumns = $this->outlet->columns;

    app(ChangeHeaderRow::class)->handle($this->produse, 2);

    $produse = $this->produse->fresh();

    expect($produse->header_row)->toBe(2)
        ->and($produse->row_count)->toBe(2)
        ->and(array_column($produse->columns, 'header'))->toBe(['Cod', 'Denumire', 'Pret'])
        ->and($produse->mapping)->toBeNull()
        ->and($produse->ai_proposal)->toBeNull()
        ->and($this->outlet->fresh()->columns)->toBe($outletColumns)
        ->and($this->outlet->fresh()->mapping)->not->toBeNull();
});

test('a header row change to an empty row is refused', function () {
    expect(fn () => app(ChangeHeaderRow::class)->handle($this->produse, 9))
        ->toThrow(ImportActionRefused::class);
});

test('skipping and changing the header row are refused while staging', function () {
    $this->import->update(['status' => ImportStatus::Staging]);

    expect(fn () => app(SkipSheet::class)->handle($this->outlet))
        ->toThrow(ImportActionRefused::class, 'The import is being processed. Wait until it has finished.');

    expect(fn () => app(ChangeHeaderRow::class)->handle($this->produse, 2))
        ->toThrow(ImportActionRefused::class, 'The import is being processed. Wait until it has finished.');

    expect($this->outlet->fresh()->skipped)->toBeFalse()
        ->and($this->produse->fresh()->header_row)->toBe(1);
});

test('sheet changes are refused past the mapping step', function () {
    $this->import->update(['status' => ImportStatus::PreviewReady]);

    expect(fn () => app(IncludeSheet::class)->handle($this->outlet))
        ->toThrow(ImportActionRefused::class, 'This change is only possible in the mapping step.');
});

test('a header row change analyzes only that sheet again', function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    AiFakes::configure();
    ColumnMappingAgent::fake([
        AiFakes::answer([[0, 'variant.sku'], [1, 'product.name', 0.9, 'ro']]),
    ]);

    $outletMapping = $this->outlet->fresh()->mapping;

    app(ChangeHeaderRow::class)->handle($this->produse, 2);

    ColumnMappingAgent::assertPromptedTimes(1);

    expect($this->produse->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($this->produse->fresh()->sheetMapping()->column(0)->target)->toBe('variant.sku')
        ->and($this->outlet->fresh()->mapping)->toBe($outletMapping)
        ->and($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});
