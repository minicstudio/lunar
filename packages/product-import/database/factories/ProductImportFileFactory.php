<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportFile;

class ProductImportFileFactory extends BaseFactory
{
    protected $model = ProductImportFile::class;

    /**
     * Define the model's default state: the first file of a new import, an
     * XLSX workbook.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_import_id' => ProductImport::factory(),
            'position' => 0,
            'original_name' => 'produse.xlsx',
            'disk' => 'local',
            'path' => 'product-imports/'.$this->faker->uuid().'.xlsx',
            'size' => 2048,
            'encoding' => null,
        ];
    }
}
