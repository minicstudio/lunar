<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Database\Factories\ProductImportRowFactory;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;

/**
 * One data row of a sheet. `raw` holds the cells as read and never changes.
 *
 * @property int $id
 * @property int $product_import_id
 * @property int $product_import_sheet_id
 * @property int $row_number
 * @property array<int|string, ?string> $raw
 * @property ?list<int> $typed
 * @property ?array<string, mixed> $normalized
 * @property ?array<string, mixed> $overrides
 * @property ?RowRole $role
 * @property ?RowRole $role_override The role staff chose when they included a row that its type skips.
 * @property ?string $source_id
 * @property ?string $parent_ref
 * @property ?string $group_key
 * @property ?string $match_value
 * @property RowStatus $status
 * @property ?RowSkipReason $skip_reason
 * @property ?list<array{level: string, field?: ?string, message: string}> $messages
 * @property ?RowAction $action
 * @property ?int $matched_product_id
 * @property ?int $matched_variant_id
 * @property ?int $product_id
 * @property ?int $product_variant_id
 */
class ProductImportRow extends BaseModel
{
    use HasFactory;

    /**
     * Rows are written in bulk and carry no timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

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
            'raw' => 'array',
            'typed' => 'array',
            'normalized' => 'array',
            'overrides' => 'array',
            'messages' => 'array',
            'role' => RowRole::class,
            'role_override' => RowRole::class,
            'status' => RowStatus::class,
            'skip_reason' => RowSkipReason::class,
            'action' => RowAction::class,
        ];
    }

    /**
     * The normalized values with staff corrections on top.
     *
     * @return array<string, mixed>
     */
    public function effective(): array
    {
        return $this->effectiveRow()->toArray();
    }

    /**
     * The normalized row with staff corrections on top.
     */
    public function effectiveRow(): NormalizedRow
    {
        return NormalizedRow::effective($this->normalized, $this->overrides);
    }

    /**
     * Whether the row creates or updates a variant. Rows staged before
     * structuring count as variants.
     */
    public function isVariantRow(): bool
    {
        return $this->role === null || $this->role->isVariant();
    }

    /**
     * Whether any message on the row is an error.
     */
    public function hasErrors(): bool
    {
        return collect($this->messages ?? [])->contains(fn (array $message): bool => ($message['level'] ?? null) === 'error');
    }

    /**
     * Scope to the data rows of the import's included sheets: rows below their
     * sheet's header row, on sheets that are not skipped.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeIncludedIn(Builder $query, ProductImport $import): Builder
    {
        return $query
            ->where('product_import_id', $import->id)
            ->whereHas('sheet', fn (Builder $sheet): Builder => $sheet
                ->where('skipped', false)
                ->whereColumn($sheet->qualifyColumn('header_row'), '<', $query->qualifyColumn('row_number')));
    }

    /**
     * The import this row belongs to.
     *
     * @return BelongsTo<ProductImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    /**
     * The sheet this row was read from.
     *
     * @return BelongsTo<ProductImportSheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(ProductImportSheet::class, 'product_import_sheet_id');
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportRowFactory
    {
        return ProductImportRowFactory::new();
    }
}
