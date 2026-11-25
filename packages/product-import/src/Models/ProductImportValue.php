<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Database\Factories\ProductImportValueFactory;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;

/**
 * One distinct category, brand, option, option value, property, or discount
 * of an import, with its match against the catalog and the staff decision.
 *
 * @property int $id
 * @property int $product_import_id
 * @property ValueType $type
 * @property string $option_key
 * @property string $source_value
 * @property string $normalized_key
 * @property ?list<string> $path
 * @property ?array{kind: string, value: int|float, starts_at: ?string, ends_at: ?string} $details
 * @property ?list<string> $sheets
 * @property ?list<string> $group_keys
 * @property int $product_count
 * @property int $variant_count
 * @property ?MatchType $match_type
 * @property ?int $matched_id
 * @property ?float $score
 * @property ?ValueAction $action
 * @property ?int $target_id
 * @property ?string $create_name
 * @property ?array{kind: string, value: int|float, starts_at: ?string, ends_at: ?string} $create_details
 * @property ?int $parent_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductImportValue extends BaseModel
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ValueType::class,
            'path' => 'array',
            'details' => 'array',
            'create_details' => 'array',
            'sheets' => 'array',
            'group_keys' => 'array',
            'match_type' => MatchType::class,
            'score' => 'float',
            'action' => ValueAction::class,
        ];
    }

    /**
     * Whether the value still needs a staff decision.
     */
    public function needsDecision(): bool
    {
        return $this->action === null;
    }

    /**
     * The existing option whose values this option value may use: its mapped
     * option, or the option its name from cells uses when staff chose an
     * existing one. Null otherwise.
     */
    public function optionOf(): ?int
    {
        if (Str::startsWith($this->option_key, 'option:')) {
            return (int) Str::after($this->option_key, 'option:');
        }

        if (! Str::startsWith($this->option_key, 'name:')) {
            return null;
        }

        $option = static::query()
            ->where('product_import_id', $this->product_import_id)
            ->where('type', ValueType::Option)
            ->where('option_key', '')
            ->where('normalized_key', Str::after($this->option_key, 'name:'))
            ->first();

        return $option?->action === ValueAction::UseExisting && $option->target_id !== null ? (int) $option->target_id : null;
    }

    /**
     * The import this value belongs to.
     *
     * @return BelongsTo<ProductImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportValueFactory
    {
        return ProductImportValueFactory::new();
    }
}
