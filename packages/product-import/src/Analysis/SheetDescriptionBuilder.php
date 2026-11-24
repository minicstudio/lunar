<?php

namespace Lunar\ProductImport\Analysis;

use Illuminate\Support\Str;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Support\TargetDefinition;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * Turns a sheet's column profiles into the analysis request payload. This is
 * where the "no full rows" rule is enforced: only the sheet name, the data row
 * count, each column's index, header, and profile with at most
 * `ai.sample_size` short samples, the allowed targets with their labels, and
 * the store's language codes are sent. No column type is sent.
 */
class SheetDescriptionBuilder
{
    public function __construct(protected TargetFieldRegistry $registry) {}

    /**
     * The payload for one sheet.
     *
     * @return array{sheet: string, row_count: int, columns: list<array<string, mixed>>, allowed_targets: list<array<string, mixed>>, languages: list<string>}
     */
    public function build(ProductImportSheet $sheet, ?int $productTypeId): array
    {
        $sampleSize = (int) config('lunar.product-import.ai.sample_size', 30);
        $sampleLength = (int) config('lunar.product-import.ai.sample_length', 100);

        $columns = array_map(fn (array $column): array => [
            'index' => (int) $column['index'],
            'header' => Str::limit((string) $column['header'], $sampleLength, ''),
            'fill_rate' => round((float) $column['fill_rate'], 2),
            'distinct_share' => round((float) $column['distinct_share'], 2),
            'samples' => array_map(
                fn (mixed $sample): string => Str::limit((string) $sample, $sampleLength, ''),
                array_slice(array_values($column['samples'] ?? []), 0, $sampleSize),
            ),
        ], $sheet->columns ?? []);

        $targets = array_map(fn (TargetDefinition $target): array => [
            'target' => $target->target,
            'label' => $target->label,
            'takes_language' => $target->takesLocale,
        ], $this->registry->targets($productTypeId));

        return [
            'sheet' => $sheet->name,
            'row_count' => $sheet->row_count,
            'columns' => $columns,
            'allowed_targets' => $targets,
            'languages' => $this->registry->languageCodes(),
        ];
    }
}
