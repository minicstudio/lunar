<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Language;
use Lunar\ProductImport\Analysis\SheetDescriptionBuilder;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a 5,000-row sheet is described with at most 30 samples per column, no column type, and no complete row', function () {
    config([
        'lunar.product-import.ai.key' => 'sk-import-secret',
        'ai.providers.openai.key' => 'sk-shared-secret',
    ]);

    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Brand::factory()->create(['name' => 'Catalog Brand Secret']);

    $import = ImportBuilder::import(ImportStatus::Analyzing);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret', 'Descriere'],
        5000,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", (string) ($i % 300).',90', str_repeat('lung ', 40).$i],
    ));

    $payload = app(SheetDescriptionBuilder::class)->build($sheet, null);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    expect(array_keys($payload))->toBe(['sheet', 'row_count', 'columns', 'allowed_targets', 'languages'])
        ->and($payload['sheet'])->toBe('Produse')
        ->and($payload['row_count'])->toBe(5000)
        ->and($payload['languages'])->toBe(['ro'])
        ->and($payload['columns'])->toHaveCount(4);

    foreach ($payload['columns'] as $column) {
        expect(array_keys($column))->toBe(['index', 'header', 'fill_rate', 'distinct_share', 'samples'])
            ->and($column)->not->toHaveKey('type')
            ->and(count($column['samples']))->toBeLessThanOrEqual(30)
            ->and(max(array_map('mb_strlen', $column['samples'])))->toBeLessThanOrEqual(100);
    }

    expect($payload['columns'][2]['samples'])->toHaveCount(30)
        ->and($payload['columns'][1]['samples'])->toHaveCount(30);

    // No sample of SKU 31 or later, so no complete row beyond the first thirty.
    $sampledSkus = $payload['columns'][0]['samples'];

    ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', '>', 1)->limit(50)->get()
        ->each(function (ProductImportRow $row) use ($sampledSkus, $json) {
            if (! in_array($row->raw[0], $sampledSkus, true)) {
                expect($json)->not->toContain('"'.$row->raw[0].'"');
            }

            expect($json)->not->toContain($row->raw[3]);
        });

    expect($json)->not->toContain('sk-import-secret')
        ->not->toContain('sk-shared-secret')
        ->not->toContain('Catalog Brand Secret');
});
