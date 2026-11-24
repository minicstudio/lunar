<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;

class ProductImportValueFactory extends BaseFactory
{
    protected $model = ProductImportValue::class;

    /**
     * Define the model's default state: a new brand set to be created.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'product_import_id' => ProductImport::factory(),
            'type' => ValueType::Brand,
            'option_key' => '',
            'source_value' => $name,
            'normalized_key' => mb_strtolower($name),
            'path' => [$name],
            'sheets' => ['Produse'],
            'product_count' => 1,
            'variant_count' => 1,
            'match_type' => MatchType::None,
            'action' => ValueAction::Create,
            'create_name' => $name,
        ];
    }
}
