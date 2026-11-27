<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportSheet;

class ProductImportSheetFactory extends BaseFactory
{
    protected $model = ProductImportSheet::class;

    /**
     * Define the model's default state: an included sheet with a header in row 1.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_import_id' => ProductImport::factory(),
            'position' => 0,
            'name' => 'Produse',
            'header_row' => 1,
            'row_count' => 0,
            'skipped' => false,
            'columns' => [],
            'analysis_attempts' => 0,
        ];
    }
}
