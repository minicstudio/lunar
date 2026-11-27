<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Database\Factories\ProductImportImageFactory;
use Lunar\ProductImport\Enums\ImageStatus;

/**
 * An image URL of an imported product, in file order, and what became of it:
 * downloaded into the product's media, already there, or failed with a
 * reason. A variant's own image links to that variant.
 *
 * @property int $id
 * @property int $product_import_id
 * @property ?int $product_import_row_id
 * @property int $product_id
 * @property ?int $product_variant_id
 * @property string $url
 * @property int $position
 * @property ImageStatus $status
 * @property ?int $media_id
 * @property ?string $error
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductImportImage extends BaseModel
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
            'status' => ImageStatus::class,
        ];
    }

    /**
     * The import this image belongs to.
     *
     * @return BelongsTo<ProductImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    /**
     * The staged row the URL came from.
     *
     * @return BelongsTo<ProductImportRow, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(ProductImportRow::class, 'product_import_row_id');
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportImageFactory
    {
        return ProductImportImageFactory::new();
    }
}
