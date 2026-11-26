<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Queries\PreviewSummary;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the preview totals count products, new and updated products, variants, warnings, and errors', function () {
    $import = ImportBuilder::import(ImportStatus::PreviewReady);
    $sheet = ProductImportSheet::factory()->for($import, 'import')->create(['header_row' => 1]);

    $rowNumber = 2;
    $statuses = [...array_fill(0, 6, RowStatus::Error), ...array_fill(0, 9, RowStatus::Warning)];

    // 21 new products with 3 variants each, and 13 updated ones with 55 variants.
    $groups = [
        ...array_map(fn (int $i) => ['key' => "new-{$i}", 'variants' => 3, 'product' => null], range(1, 21)),
        ...array_map(fn (int $i) => ['key' => "upd-{$i}", 'variants' => $i <= 10 ? 4 : 5, 'product' => 1000 + $i], range(1, 13)),
    ];

    foreach ($groups as $group) {
        foreach (range(1, $group['variants']) as $ignored) {
            ProductImportRow::factory()->for($sheet, 'sheet')->create([
                'row_number' => $rowNumber++,
                'group_key' => $group['key'],
                'matched_product_id' => $group['product'],
                'status' => array_shift($statuses) ?? RowStatus::Valid,
            ]);
        }
    }

    // A product whose rows staff skipped is not counted.
    ProductImportRow::factory()->for($sheet, 'sheet')->count(2)->sequence(['row_number' => 200], ['row_number' => 201])->create([
        'group_key' => 'skipped',
        'status' => RowStatus::Skipped,
        'skip_reason' => RowSkipReason::Staff,
    ]);

    $summary = app(PreviewSummary::class);

    expect($summary->totals($import))->toMatchArray([
        'products' => 34,
        'new' => 21,
        'updated' => 13,
        'variants' => 118,
        'warnings' => 9,
        'errors' => 6,
        'skipped' => 2,
        'importable' => 112,
    ])->and($summary->rows($import, PreviewSummary::FILTER_NEW)->count())->toBe(63)
        ->and($summary->rows($import, PreviewSummary::FILTER_UPDATE)->count())->toBe(55)
        ->and($summary->rows($import, PreviewSummary::FILTER_PROBLEMS)->count())->toBe(15)
        ->and($summary->rows($import, PreviewSummary::FILTER_SKIPPED)->count())->toBe(2)
        ->and($summary->rows($import)->count())->toBe(120);
});
