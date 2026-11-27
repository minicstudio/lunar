<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Exceptions\InvalidApiKeyException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\RetrySheetAnalysis;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    $this->productType = ProductType::factory()->create();
    AiFakes::configure();

    $this->import = ImportBuilder::import(ImportStatus::Analyzing);
    $this->produse = ImportBuilder::sheet($this->import, 'Produse', [
        ['Denumire', 'Cod parinte', 'Cod', 'Observatii'],
        ['Tricou', 'TB-1', 'TB-1-S', 'nota'],
    ], 1, ['analysis_status' => SheetAnalysisStatus::Pending]);
    $this->outlet = ImportBuilder::sheet($this->import, 'Outlet', [
        ['Cod', 'Denumire'],
        ['OUT-1', 'Geacă'],
    ], 1, ['analysis_status' => SheetAnalysisStatus::Pending]);
    $this->info = ImportBuilder::sheet($this->import, 'Info', [
        ['Nota'],
        ['Exportat'],
    ], 1, ['skipped' => true, 'analysis_status' => null]);
});

function produseAnswer(): array
{
    return AiFakes::answer([
        [0, 'product.name', 0.96, 'ro'],
        [1, 'group_key', 0.74],
        [2, 'variant.sku', 0.97],
        [3, 'ignore', 0.40],
    ], ['strategy' => 'by_column', 'column' => 1, 'confidence' => 0.74]);
}

function outletAnswer(): array
{
    return AiFakes::answer([[0, 'variant.sku', 0.98], [1, 'product.name', 0.9, 'ro']]);
}

/**
 * A provider error response for the given status.
 */
function providerResponse(int $status, string $body = '{"error":{"message":"failed"}}'): Response
{
    return new Response(new Psr7Response($status, ['Content-Type' => 'application/json'], $body));
}

test('two included sheets give two prompts and a skipped sheet gives none', function () {
    ColumnMappingAgent::fake([produseAnswer(), outletAnswer()]);

    app(AnalysisCoordinator::class)->startForImport($this->import);

    ColumnMappingAgent::assertPromptedTimes(2);

    expect($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping)
        ->and($this->import->fresh()->ai_provider)->toBe('openai')
        ->and($this->produse->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($this->outlet->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($this->info->fresh()->analysis_status)->toBeNull();
});

test('confidences decide what is selected, highlighted, and left unmapped', function () {
    ColumnMappingAgent::fake([produseAnswer()]);

    AiFakes::runLikeWorker($this->produse->id);

    $sheet = $this->produse->fresh();
    $mapping = $sheet->sheetMapping();

    expect($sheet->ai_proposal)->toBe(produseAnswer())
        ->and($sheet->analysis_attempts)->toBe(1)
        ->and($mapping->productTypeId)->toBe($this->productType->id)
        ->and($mapping->column(0)->target)->toBe('product.name')
        ->and($mapping->column(0)->flagged)->toBeFalse()
        ->and($mapping->column(1)->target)->toBe('group_key')
        ->and($mapping->column(1)->flagged)->toBeTrue()
        ->and($mapping->column(3)->target)->toBeNull()
        ->and($mapping->column(3)->suggestedTarget)->toBe('ignore');
});

test('a timeout then a success leaves no error', function () {
    ColumnMappingAgent::fake(AiFakes::sequence([
        ProviderConnectionException::forProvider('openai'),
        produseAnswer(),
    ]));

    AiFakes::runLikeWorker($this->produse->id);

    $sheet = $this->produse->fresh();

    expect($sheet->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($sheet->analysis_error)->toBeNull()
        ->and($sheet->analysis_attempts)->toBe(2);
});

test('three timeouts give provider_timeout with three attempts', function () {
    ColumnMappingAgent::fake(AiFakes::sequence([ProviderConnectionException::forProvider('openai')]));

    AiFakes::runLikeWorker($this->produse->id);

    $sheet = $this->produse->fresh();

    ColumnMappingAgent::assertPromptedTimes(3);

    expect($sheet->analysis_status)->toBe(SheetAnalysisStatus::Failed)
        ->and($sheet->analysis_error['code'])->toBe('provider_timeout')
        ->and($sheet->analysis_error['message'])->toBe('The AI provider did not respond. This is usually temporary. Try again.')
        ->and($sheet->analysis_error['attempt'])->toBe(3)
        ->and($sheet->analysis_attempts)->toBe(3);
});

test('a rejected key gives auth_failed after one attempt', function () {
    ColumnMappingAgent::fake(AiFakes::sequence([
        new InvalidApiKeyException('openai', providerResponse(401)),
        produseAnswer(),
    ]));

    AiFakes::runLikeWorker($this->produse->id);

    ColumnMappingAgent::assertPromptedTimes(1);

    expect($this->produse->fresh()->analysis_error)->toMatchArray([
        'code' => 'auth_failed',
        'message' => 'The AI provider rejected the API key. The configuration needs to be checked.',
        'attempt' => 1,
    ]);
});

test('failures map to their codes', function (Closure $response, string $code, int $prompts) {
    ColumnMappingAgent::fake(AiFakes::sequence([$response()]));

    AiFakes::runLikeWorker($this->produse->id);

    ColumnMappingAgent::assertPromptedTimes($prompts);

    expect($this->produse->fresh()->analysis_error['code'])->toBe($code);
})->with([
    'rate limit' => [fn () => RateLimitedException::forProvider('openai'), 'rate_limited', 3],
    'server error' => [fn () => new RequestException(providerResponse(500)), 'provider_error', 3],
    'forbidden' => [fn () => new RequestException(providerResponse(403)), 'auth_failed', 1],
    'unusable answer' => [fn () => AiFakes::answer([[0, 'variant.sku'], [2, 'variant.sku']]), 'invalid_response', 1],
    'no product data' => [fn () => AiFakes::answer([[3, 'ignore']]), 'no_product_data', 1],
]);

test('the unusable answer message names the reason', function () {
    ColumnMappingAgent::fake([AiFakes::answer([[0, 'variant.sku'], [2, 'variant.sku']])]);

    AiFakes::runLikeWorker($this->produse->id);

    expect($this->produse->fresh()->analysis_error['message'])
        ->toBe('The AI answer could not be used: two columns were marked as Variant SKU. Try again.');
});

test('a stored message never contains the key', function () {
    AiFakes::configure('sk-import-very-secret-key');

    ColumnMappingAgent::fake(AiFakes::sequence([
        new RequestException(providerResponse(401, '{"error":{"message":"Incorrect API key provided: sk-import-very-secret-key"}}')),
    ]));

    AiFakes::runLikeWorker($this->produse->id);

    $stored = json_encode($this->produse->fresh()->analysis_error);

    expect($this->produse->fresh()->analysis_error['code'])->toBe('auth_failed')
        ->and($stored)->not->toContain('sk-import-very-secret-key')
        ->and($stored)->toContain('[redacted]');
});

test('an import becomes analysis failed when a sheet fails and awaits mapping once it is retried', function () {
    ColumnMappingAgent::fake(AiFakes::sequence([
        new InvalidApiKeyException('openai', providerResponse(401)),
        outletAnswer(),
        produseAnswer(),
    ]));

    app(AnalysisCoordinator::class)->startForImport($this->import);

    expect($this->import->fresh()->status)->toBe(ImportStatus::AnalysisFailed)
        ->and($this->produse->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Failed);

    $outletProposal = $this->outlet->fresh()->ai_proposal;

    app(RetrySheetAnalysis::class)->handle($this->produse->fresh());

    expect($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping)
        ->and($this->produse->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($this->produse->fresh()->analysis_error)->toBeNull()
        ->and($this->outlet->fresh()->ai_proposal)->toBe($outletProposal);

    ColumnMappingAgent::assertPromptedTimes(3);
});

test('the analysis changes no catalog or staging row', function () {
    $rows = ProductImportRow::query()->orderBy('id')->get()->toArray();

    ColumnMappingAgent::fake([produseAnswer(), outletAnswer()]);

    app(AnalysisCoordinator::class)->startForImport($this->import);

    expect(ProductImportRow::query()->orderBy('id')->get()->toArray())->toBe($rows)
        ->and(Product::count())->toBe(0)
        ->and(ProductImportSheet::count())->toBe(3);
});

test('a cancelled import is not analyzed', function () {
    ColumnMappingAgent::fake([produseAnswer()]);

    $this->import->update(['status' => ImportStatus::Cancelled]);

    AiFakes::runLikeWorker($this->produse->id);

    ColumnMappingAgent::assertNeverPrompted();
});

test('two sheets keep their own separators whichever finishes first, and the import options do not change', function (bool $outletFirst) {
    $import = ImportBuilder::import(ImportStatus::Analyzing);
    $produse = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Categorii'],
        ['TB-1', 'Tricou', 'Femei > Rochii, Femei > Noutăți'],
    ], 1, ['analysis_status' => SheetAnalysisStatus::Pending]);
    $outlet = ImportBuilder::sheet($import, 'Outlet', [
        ['Cod', 'Denumire', 'Categorii'],
        ['OUT-1', 'Geacă', 'Femei/Rochii|Femei/Noutăți'],
    ], 1, ['analysis_status' => SheetAnalysisStatus::Pending]);

    $answer = fn (string $list, string $path): array => AiFakes::answer([
        [0, 'variant.sku', 0.98],
        [1, 'product.name', 0.95, 'ro'],
        [2, 'product.category', 0.9, null, null, null, null, ['list_separator' => $list, 'path_separator' => $path]],
    ]);

    $order = $outletFirst ? [$outlet, $produse] : [$produse, $outlet];

    ColumnMappingAgent::fake($outletFirst ? [$answer('|', '/'), $answer(',', '>')] : [$answer(',', '>'), $answer('|', '/')]);

    foreach ($order as $sheet) {
        AiFakes::runLikeWorker($sheet->id);
    }

    expect($produse->fresh()->sheetMapping()->column(2)->format->listSeparator)->toBe(',')
        ->and($produse->fresh()->sheetMapping()->column(2)->format->pathSeparator)->toBe('>')
        ->and($outlet->fresh()->sheetMapping()->column(2)->format->listSeparator)->toBe('|')
        ->and($outlet->fresh()->sheetMapping()->column(2)->format->pathSeparator)->toBe('/')
        ->and($import->fresh()->options)->toBeNull();
})->with([
    'outlet first' => [true],
    'produse first' => [false],
]);
