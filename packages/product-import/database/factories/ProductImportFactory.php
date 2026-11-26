<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportFile;

class ProductImportFactory extends BaseFactory
{
    protected $model = ProductImport::class;

    /**
     * Define the model's default state: a fresh upload. `withFile()` adds its file.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => null,
            'status' => ImportStatus::Uploaded,
            'options' => null,
            'stats' => null,
        ];
    }

    /**
     * Give the import an uploaded file with the given name.
     */
    public function withFile(string $originalName = 'produse.xlsx'): static
    {
        return $this->has(ProductImportFile::factory()->state(['original_name' => $originalName]), 'files');
    }

    /**
     * Put the import in the given status.
     */
    public function status(ImportStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
