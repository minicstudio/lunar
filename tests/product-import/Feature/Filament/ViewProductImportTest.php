<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Filament\Livewire\ImportProgress;
use Lunar\ProductImport\Filament\Livewire\ImportResult;
use Lunar\ProductImport\Filament\Livewire\PreviewStep;
use Lunar\ProductImport\Filament\Livewire\SheetMappingStep;
use Lunar\ProductImport\Filament\Livewire\ValueReviewStep;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ViewProductImport;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    AiFakes::configure();
    $this->actingAs($this->makeStaff(admin: true), 'staff');
});

test('the page shows the step component of the import status', function (ImportStatus $status, string $component) {
    $import = ImportBuilder::import($status);

    Livewire::test(ViewProductImport::class, ['record' => $import->id])
        ->assertSeeLivewire($component)
        ->assertDontSee('The file is being read.');
})->with([
    'analyzing' => [ImportStatus::Analyzing, SheetMappingStep::class],
    'analysis failed' => [ImportStatus::AnalysisFailed, SheetMappingStep::class],
    'awaiting mapping' => [ImportStatus::AwaitingMapping, SheetMappingStep::class],
    'staging' => [ImportStatus::Staging, SheetMappingStep::class],
    'reviewing values' => [ImportStatus::ReviewingValues, ValueReviewStep::class],
    'preview ready' => [ImportStatus::PreviewReady, PreviewStep::class],
    'importing' => [ImportStatus::Importing, ImportProgress::class],
    'completed' => [ImportStatus::Completed, ImportResult::class],
    'completed with errors' => [ImportStatus::CompletedWithErrors, ImportResult::class],
    'failed' => [ImportStatus::Failed, ImportResult::class],
    'cancelled' => [ImportStatus::Cancelled, ImportResult::class],
]);

test('an uploaded import says the file is being read, polls, and marks the first step', function () {
    $import = ImportBuilder::import(ImportStatus::Uploaded);

    Livewire::test(ViewProductImport::class, ['record' => $import->id])
        ->assertSee('The file is being read.')
        ->assertSeeHtml('wire:poll.3s="refreshImport"')
        ->assertDontSeeLivewire(SheetMappingStep::class)
        ->assertViewHas('steps', fn (array $steps): bool => array_column($steps, 'color') === ['primary', 'gray', 'gray', 'gray', 'gray']);
});

test('a finished import does not poll and marks the earlier steps done', function () {
    $import = ImportBuilder::import(ImportStatus::Completed);

    Livewire::test(ViewProductImport::class, ['record' => $import->id])
        ->assertDontSeeHtml('wire:poll.3s="refreshImport"')
        ->assertViewHas('steps', fn (array $steps): bool => array_column($steps, 'color') === ['success', 'success', 'success', 'success', 'primary']);
});
