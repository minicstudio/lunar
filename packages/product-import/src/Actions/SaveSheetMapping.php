<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Str;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\InvalidMapping;
use Lunar\ProductImport\Mapping\MappingValidator;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Validates and stores a sheet's mapping and the import options.
 */
class SaveSheetMapping
{
    use GuardsImportStatus;

    public function __construct(protected MappingValidator $validator) {}

    /**
     * @throws InvalidMapping
     */
    public function handle(ProductImportSheet $sheet, SheetMapping $mapping, ?ImportOptions $options = null): ProductImportSheet
    {
        $import = $sheet->import;

        $this->ensureMappingStep($import);

        $mapping = $this->prepare($sheet, $mapping);

        $this->validator->validate($mapping, $sheet->name);

        $sheet->forceFill(['mapping' => $mapping->toArray()])->save();

        if ($options !== null) {
            $import->forceFill(['options' => $options->toArray()])->save();
        }

        return $sheet;
    }

    /**
     * Fill in what follows from the mapping: the sheet's header row, new
     * option names from the column header, and the grouping column.
     */
    public function prepare(ProductImportSheet $sheet, SheetMapping $mapping): SheetMapping
    {
        $columns = array_map(function (ColumnMapping $column): ColumnMapping {
            if ($column->is(TargetField::VariantOption) && $column->optionId === null && Str::trim((string) $column->optionName) === '') {
                $column->optionName = $column->header;
            }

            if (! $column->is(TargetField::VariantOption)) {
                $column->optionId = null;
                $column->optionName = null;
            }

            return $column;
        }, $mapping->columns);

        $grouping = $mapping->grouping;

        if ($grouping->strategy === GroupingStrategy::ByColumn) {
            $grouping = new Grouping(
                GroupingStrategy::ByColumn,
                $mapping->groupKeyColumn()?->index ?? $grouping->column,
                $grouping->confidence,
            );
        }

        $prepared = clone $mapping;
        $prepared->headerRow = $sheet->header_row;
        $prepared->grouping = $grouping;
        $prepared->columns = $columns;
        $prepared->weightUnit = filled($mapping->weightUnit) ? $mapping->weightUnit : null;

        return $prepared;
    }
}
