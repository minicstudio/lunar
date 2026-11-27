<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;

class ProductImportRowFactory extends BaseFactory
{
    protected $model = ProductImportRow::class;

    /**
     * Define the model's default state: an unstaged row of a new sheet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_import_sheet_id' => ProductImportSheet::factory(),
            'product_import_id' => fn (array $attributes): int => ProductImportSheet::query()
                ->findOrFail($attributes['product_import_sheet_id'])
                ->product_import_id,
            'row_number' => 2,
            'raw' => [],
            'typed' => [],
            'status' => RowStatus::Pending,
        ];
    }
}
