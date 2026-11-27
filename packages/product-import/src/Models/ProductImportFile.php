<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Database\Factories\ProductImportFileFactory;

/**
 * One uploaded file of an import. A CSV file is one sheet; a workbook has a
 * sheet per worksheet. The stored file is deleted when the import is pruned,
 * and this record keeps its name.
 *
 * @property int $id
 * @property int $product_import_id
 * @property int $position
 * @property string $original_name
 * @property string $disk
 * @property ?string $path
 * @property ?int $size
 * @property ?string $encoding The CSV encoding chosen at upload; null means UTF-8.
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductImportFile extends BaseModel
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
            'position' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * The lowercase extension of the file.
     */
    public function extension(): string
    {
        return Str::lower(pathinfo((string) ($this->path ?: $this->original_name), PATHINFO_EXTENSION));
    }

    /**
     * Whether the file is a CSV file.
     */
    public function isCsv(): bool
    {
        return $this->extension() === 'csv';
    }

    /**
     * The import this file belongs to.
     *
     * @return BelongsTo<ProductImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    /**
     * The sheets read from this file.
     *
     * @return HasMany<ProductImportSheet, $this>
     */
    public function sheets(): HasMany
    {
        return $this->hasMany(ProductImportSheet::class, 'product_import_file_id')->orderBy('position');
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportFileFactory
    {
        return ProductImportFileFactory::new();
    }
}
