<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Database\Factories\ProductImportSheetFactory;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;

/**
 * One sheet of an import (a CSV file is one sheet).
 *
 * @property int $id
 * @property int $product_import_id
 * @property ?int $product_import_file_id
 * @property int $position
 * @property string $name
 * @property int $header_row
 * @property int $row_count
 * @property bool $skipped
 * @property ?list<array<string, mixed>> $columns
 * @property ?SheetAnalysisStatus $analysis_status
 * @property ?array<string, mixed> $ai_proposal
 * @property ?array{code: string, message: string, raw?: ?string} $analysis_error
 * @property int $analysis_attempts
 * @property ?array<string, mixed> $mapping
 * @property ?int $offered_profile_id
 * @property ?float $offered_profile_score
 * @property ?int $profile_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductImportSheet extends BaseModel
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
            'skipped' => 'boolean',
            'columns' => 'array',
            'analysis_status' => SheetAnalysisStatus::class,
            'ai_proposal' => 'array',
            'analysis_error' => 'array',
            'mapping' => 'array',
            'offered_profile_score' => 'float',
        ];
    }

    /**
     * The stored mapping, or an unmapped one built from the column profiles.
     */
    public function sheetMapping(): SheetMapping
    {
        if (is_array($this->mapping) && $this->mapping !== []) {
            return SheetMapping::from($this->mapping);
        }

        return new SheetMapping(
            headerRow: $this->header_row,
            columns: array_map(
                fn (array $column): ColumnMapping => new ColumnMapping((int) $column['index'], (string) $column['header']),
                $this->columns ?? [],
            ),
        );
    }

    /**
     * Whether staff or a profile gave the sheet a mapping.
     */
    public function hasMapping(): bool
    {
        return is_array($this->mapping) && $this->mapping !== [];
    }

    /**
     * The import this sheet belongs to.
     *
     * @return BelongsTo<ProductImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    /**
     * The uploaded file this sheet was read from.
     *
     * @return BelongsTo<ProductImportFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ProductImportFile::class, 'product_import_file_id');
    }

    /**
     * The staged rows of this sheet.
     *
     * @return HasMany<ProductImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class);
    }

    /**
     * The profile offered because its headers match.
     *
     * @return BelongsTo<ProductImportProfile, $this>
     */
    public function offeredProfile(): BelongsTo
    {
        return $this->belongsTo(ProductImportProfile::class, 'offered_profile_id');
    }

    /**
     * The profile applied to this sheet.
     *
     * @return BelongsTo<ProductImportProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProductImportProfile::class, 'profile_id');
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportSheetFactory
    {
        return ProductImportSheetFactory::new();
    }
}
