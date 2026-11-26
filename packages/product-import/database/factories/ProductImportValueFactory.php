<?php

namespace Lunar\ProductImport\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Normalizing\RowDiscount;

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

    /**
     * A discount from the file, set to be created with the file's values:
     * by default 30% until the end of 31 December 2026.
     *
     * @param  int|float  $value  The percent, or the amount in minor units.
     */
    public function discount(string $kind = RowDiscount::PERCENT, int|float $value = 30, ?string $startsAt = null, ?string $endsAt = '2026-12-31 23:59:59'): static
    {
        $discount = new RowDiscount($kind, $value, $startsAt, $endsAt);

        return $this->state(fn (): array => [
            'type' => ValueType::Discount,
            'option_key' => '',
            'source_value' => $discount->describe(),
            'normalized_key' => $discount->key(),
            'path' => null,
            'details' => $discount->toArray(),
            'create_name' => $discount->describe(),
            'create_details' => $discount->toArray(),
        ]);
    }
}
