<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Admin\Models\Staff;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Database\Factories\ProductImportProfileFactory;

/**
 * A saved sheet mapping, offered again for files with matching headers.
 *
 * @property int $id
 * @property string $name
 * @property string $header_fingerprint
 * @property list<string> $headers
 * @property array<string, mixed> $mapping
 * @property ?array<string, mixed> $options
 * @property int $usage_count
 * @property ?Carbon $last_used_at
 * @property ?int $staff_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductImportProfile extends BaseModel
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
            'headers' => 'array',
            'mapping' => 'array',
            'options' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * The staff member who saved the profile.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportProfileFactory
    {
        return ProductImportProfileFactory::new();
    }
}
