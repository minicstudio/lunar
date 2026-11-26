<?php

namespace Lunar\Tests\ProductImport\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Lunar\ProductImport\Analysis\SheetProfileBuilder;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Jobs\CommitProductGroups;
use Lunar\ProductImport\Jobs\CommitTaxonomy;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Persisting\CommitChain;
use Lunar\ProductImport\Staging\ImportNormalizer;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Taxonomy\ValueCollector;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Builds imports in a given state straight in the staging tables, as if the
 * file had been read, without a file and without the AI.
 */
class ImportBuilder
{
    /**
     * An import in the given status, with one file that is not on disk. The
     * file attributes `original_name`, `disk`, `path`, and `encoding` go to
     * that file.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function import(ImportStatus $status = ImportStatus::AwaitingMapping, array $attributes = []): ProductImport
    {
        $fileKeys = ['original_name', 'disk', 'path', 'encoding'];

        $import = ProductImport::factory()->create([
            'status' => $status,
            ...Arr::except($attributes, $fileKeys),
        ]);

        $import->files()->create([
            'position' => 0,
            'original_name' => 'produse.xlsx',
            'disk' => 'local',
            'path' => 'product-imports/missing.xlsx',
            ...Arr::only($attributes, $fileKeys),
        ]);

        return $import;
    }

    /**
     * An uploaded import whose files are fixture files put on the `local`
     * disk, in order, as the upload stores them.
     *
     * @param  array<string, string>  $files  Original name => fixture path.
     * @param  array<string, mixed>  $attributes
     */
    public static function uploaded(array $files, array $attributes = []): ProductImport
    {
        $import = ProductImport::factory()->create(['status' => ImportStatus::Uploaded, ...$attributes]);
        $position = 0;

        foreach ($files as $originalName => $fixturePath) {
            $path = 'product-imports/'.uniqid().'.'.pathinfo($fixturePath, PATHINFO_EXTENSION);

            Storage::disk('local')->put($path, (string) file_get_contents($fixturePath));

            $import->files()->create([
                'position' => $position++,
                'original_name' => $originalName,
                'disk' => 'local',
                'path' => $path,
                'size' => filesize($fixturePath),
            ]);
        }

        return $import;
    }

    /**
     * The path of an import's first file.
     */
    public static function filePath(ProductImport $import): string
    {
        return (string) $import->files()->value('path');
    }

    /**
     * A sheet whose rows (keyed by row number, or a list starting at row 1)
     * are stored as read, with its header row and column profiles built.
     *
     * @param  array<int, list<?string>>  $rows
     * @param  array<string, mixed>  $attributes
     */
    public static function sheet(ProductImport $import, string $name, array $rows, ?int $headerRow = 1, array $attributes = []): ProductImportSheet
    {
        $sheet = $import->sheets()->create([
            'position' => $import->sheets()->count(),
            'name' => $name,
            'header_row' => 1,
            'row_count' => 0,
            'skipped' => false,
            'analysis_status' => SheetAnalysisStatus::Completed,
            ...$attributes,
        ]);

        $isList = array_is_list($rows);
        $records = [];

        foreach ($rows as $key => $cells) {
            $filtered = array_filter($cells, fn (?string $cell): bool => $cell !== null && trim($cell) !== '');

            if ($filtered === []) {
                continue;
            }

            $records[] = [
                'product_import_id' => $import->id,
                'product_import_sheet_id' => $sheet->id,
                'row_number' => $isList ? $key + 1 : $key,
                'raw' => json_encode((object) $filtered, JSON_UNESCAPED_UNICODE),
                'status' => RowStatus::Pending->value,
            ];
        }

        foreach (array_chunk($records, 250) as $batch) {
            $sheet->rows()->getQuery()->insert($batch);
        }

        return app(SheetProfileBuilder::class)->build($sheet, $headerRow);
    }

    /**
     * Store a mapping on a sheet: targets by column index, as
     * `index => target` or `index => [target, locale (of a target that takes
     * one, "ro" by default, or of an attribute) or option name or pair
     * number, format]`, where the format may also hold the column's
     * conventions. A column mapped to a number target without a format
     * gets a decimal comma and a dot thousands separator, a date column the
     * format `Y-m-d G:i:s`, and a list, category, or packed-items column the
     * separators it lacks (`withSeparators()`).
     *
     * @param  array<int, string|array{0: string, 1?: string|int|null, 2?: ?ColumnFormat}>  $targets
     * @param  array<int, ColumnFormat>  $formats  Formats by column index.
     */
    public static function map(ProductImportSheet $sheet, array $targets, ?int $productTypeId, ?int $groupColumn = null, GroupingStrategy $strategy = GroupingStrategy::PerRow, ?string $weightUnit = null, array $formats = [], ?string $lengthUnit = null): ProductImportSheet
    {
        $columns = [];

        foreach ($sheet->columns ?? [] as $column) {
            $index = (int) $column['index'];
            $spec = $targets[$index] ?? null;
            [$target, $extra, $format] = is_array($spec) ? [$spec[0], $spec[1] ?? null, $spec[2] ?? null] : [$spec, null, null];
            $format ??= $formats[$index] ?? null;

            if ($format === null && $target !== null && TargetField::tryFrom($target)?->isNumber()) {
                $format = ColumnFormat::number(',', '.');
            }

            if ($format === null && $target !== null && TargetField::tryFrom($target)?->isDate()) {
                $format = ColumnFormat::date('Y-m-d G:i:s');
            }

            $format = self::withSeparators($target, $format);

            $columns[] = new ColumnMapping(
                index: $index,
                header: (string) $column['header'],
                target: $target,
                locale: TargetField::tryFrom((string) $target)?->takesLocale() ? ($extra ?? 'ro') : (TargetField::isAttribute($target) && is_string($extra) ? $extra : null),
                optionName: $target === 'variant.option' ? ($extra ?? (string) $column['header']) : null,
                format: $format,
                pair: in_array($target, ['variant.option_name', 'variant.option_value'], true) ? (int) ($extra ?? 1) : null,
            );
        }

        $sheet->forceFill(['mapping' => (new SheetMapping(
            headerRow: $sheet->header_row,
            productTypeId: $productTypeId,
            grouping: $groupColumn !== null ? new Grouping(GroupingStrategy::ByColumn, $groupColumn) : new Grouping($strategy),
            columns: $columns,
            weightUnit: $weightUnit,
            lengthUnit: $lengthUnit,
        ))->toArray()])->save();

        return $sheet;
    }

    /**
     * The format with the separators its target needs and lacks, as the
     * cells of these tests write them: "," between list items, ">" between
     * category levels, and ":" between item parts.
     */
    public static function withSeparators(?string $target, ?ColumnFormat $format): ?ColumnFormat
    {
        $kinds = TargetField::tryFrom((string) $target)?->separators(($format?->itemParts ?? []) !== []) ?? [];

        if ($kinds === []) {
            return $format;
        }

        $format = $format !== null ? clone $format : new ColumnFormat;
        $format->listSeparator ??= in_array('list', $kinds, true) ? ',' : null;
        $format->pathSeparator ??= in_array('path', $kinds, true) ? '>' : null;
        $format->itemSeparator ??= in_array('item', $kinds, true) ? ':' : null;

        return $format;
    }

    /**
     * Normalize, structure, collect values, and validate, as staging does.
     */
    public static function stage(ProductImport $import, ?ImportOptions $options = null): ProductImport
    {
        if ($options !== null) {
            $import->forceFill(['options' => $options->toArray()])->save();
        }

        app(ImportNormalizer::class)->normalize($import);
        app(RowStructurer::class)->structure($import);
        app(ValueCollector::class)->collect($import);
        app(ImportValidator::class)->validate($import);

        return $import->refresh();
    }

    /**
     * Write the import's importable groups the way the commit chain does, but
     * in this process: the taxonomy first, then the product groups.
     */
    public static function commit(ProductImport $import): ProductImport
    {
        $groups = app(CommitChain::class)->importableGroups($import);

        $import->forceFill(['status' => ImportStatus::Importing, 'started_at' => now()])->save();

        app()->call([new CommitTaxonomy($import->id), 'handle']);
        app()->call([new CommitProductGroups($import->id, $groups), 'handle']);

        return $import->refresh();
    }

    /**
     * Rows for a sheet: the header, then `$count` data rows from a builder.
     *
     * @param  list<string>  $header
     * @param  callable(int): list<?string>  $row
     * @return list<list<?string>>
     */
    public static function rows(array $header, int $count, callable $row): array
    {
        return [$header, ...array_map($row, range(1, $count))];
    }
}
