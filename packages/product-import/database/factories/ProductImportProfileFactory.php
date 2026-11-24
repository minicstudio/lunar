<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Models\ProductImportProfile;

class ProductImportProfileFactory extends BaseFactory
{
    protected $model = ProductImportProfile::class;

    /**
     * Define the model's default state: an unused profile without columns.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'header_fingerprint' => hash('sha256', ''),
            'headers' => [],
            'mapping' => [],
            'options' => null,
            'usage_count' => 0,
        ];
    }
}
