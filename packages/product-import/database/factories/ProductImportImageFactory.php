<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\Models\Product;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;

class ProductImportImageFactory extends BaseFactory
{
    protected $model = ProductImportImage::class;

    /**
     * Define the model's default state: a pending image of a new product.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_import_id' => ProductImport::factory(),
            'product_id' => Product::factory(),
            'url' => $this->faker->imageUrl(),
            'position' => 0,
            'status' => ImageStatus::Pending,
        ];
    }
}
