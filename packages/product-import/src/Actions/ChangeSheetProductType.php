<?php

namespace Lunar\ProductImport\Actions;

use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * Gives a sheet another product type and unmaps the attribute targets that
 * the new type does not have.
 */
class ChangeSheetProductType
{
    use GuardsImportStatus;

    public function __construct(protected TargetFieldRegistry $registry) {}

    /**
     * @return list<string> The names of the attributes that were unmapped.
     */
    public function handle(ProductImportSheet $sheet, int $productTypeId): array
    {
        $this->ensureMappingStep($sheet->import);

        if (! ProductType::query()->whereKey($productTypeId)->exists()) {
            throw ImportActionRefused::because('unknown_product_type');
        }

        $mapping = $sheet->sheetMapping();
        $dropped = [];

        $mapping->columns = array_map(function (ColumnMapping $column) use ($mapping, $productTypeId, &$dropped): ColumnMapping {
            if (TargetField::isAttribute($column->target) && ! $this->registry->exists((string) $column->target, $productTypeId)) {
                $dropped[] = $this->registry->label((string) $column->target, $mapping->productTypeId);
                $column->target = null;
                $column->locale = null;
            }

            return $column;
        }, $mapping->columns);

        $mapping->productTypeId = $productTypeId;
        $mapping->headerRow = $sheet->header_row;

        $sheet->forceFill(['mapping' => $mapping->toArray()])->save();

        return $dropped;
    }
}
