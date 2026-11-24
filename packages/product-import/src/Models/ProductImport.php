<?php

namespace Lunar\ProductImport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lunar\Admin\Models\Staff;
use Lunar\Base\BaseModel;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Database\Factories\ProductImportFactory;
use Lunar\ProductImport\Enums\ImportStatus;

/**
 * One upload of one or more files, and everything staged for it.
 *
 * @property int $id
 * @property ?int $staff_id
 * @property ImportStatus $status
 * @property ?array<string, mixed> $options
 * @property ?array<string, int> $resolved_options
 * @property ?array<string, mixed> $stats
 * @property ?string $batch_id
 * @property ?string $ai_provider
 * @property ?string $ai_model
 * @property ?string $failure_message
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property ?Carbon $pruned_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ProductImport extends BaseModel
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Delete the staged data with the import, also where the database does
     * not enforce the cascading foreign keys.
     */
    protected static function booted(): void
    {
        static::deleting(function (ProductImport $import): void {
            $import->images()->delete();
            $import->values()->delete();
            $import->rows()->delete();
            ProductImportSheet::query()->where('product_import_id', $import->id)->delete();
            ProductImportFile::query()->where('product_import_id', $import->id)->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'options' => 'array',
            'resolved_options' => 'array',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pruned_at' => 'datetime',
        ];
    }

    /**
     * Move to the given status, but only while the stored status is one of
     * the expected ones (the current status by default) and the transition is
     * allowed. A second click or a second job therefore changes nothing.
     *
     * @param  ImportStatus|list<ImportStatus>|null  $from
     * @param  array<string, mixed>  $attributes  Extra attributes saved with the transition.
     */
    public function transitionTo(ImportStatus $to, ImportStatus|array|null $from = null, array $attributes = []): bool
    {
        $expected = array_values(array_filter(
            $from === null ? [$this->status] : Arr::wrap($from),
            fn (ImportStatus $status): bool => $status->canTransitionTo($to),
        ));

        if ($expected === []) {
            return false;
        }

        $updated = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', array_map(fn (ImportStatus $status): string => $status->value, $expected))
            ->update(['status' => $to->value, 'updated_at' => now()]);

        if ($updated !== 1) {
            $this->refresh();

            return false;
        }

        $this->status = $to;
        $this->syncOriginalAttribute('status');

        if ($attributes !== []) {
            $this->forceFill($attributes)->save();
        }

        return true;
    }

    /**
     * Read the stored status again and say whether it is still the given one.
     * Jobs call this between chunks so a cancel stops them.
     */
    public function stillHasStatus(ImportStatus $status): bool
    {
        $stored = static::query()->whereKey($this->getKey())->toBase()->value('status');

        return $stored === $status->value;
    }

    /**
     * The import options, with defaults for what was never set.
     */
    public function importOptions(): ImportOptions
    {
        return ImportOptions::restore($this->options);
    }

    /**
     * Whether the staged rows, values, and file were removed.
     */
    public function isPruned(): bool
    {
        return $this->pruned_at !== null;
    }

    /**
     * The staff member who uploaded the file.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * The uploaded files, in upload order.
     *
     * @return HasMany<ProductImportFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProductImportFile::class)->orderBy('position');
    }

    /**
     * The name of the first file, and how many other files the import has:
     * "products_import.csv (+1)".
     */
    public function fileLabel(): string
    {
        $files = $this->relationLoaded('files') ? $this->files : $this->files()->get(['id', 'product_import_id', 'position', 'original_name']);
        $first = (string) ($files->first()?->original_name ?? '');

        return $files->count() > 1
            ? __('lunarpanel.product-import::ui.list.more_files', ['name' => $first, 'count' => $files->count() - 1])
            : $first;
    }

    /**
     * The sheets of the files, in upload and file order.
     *
     * @return HasMany<ProductImportSheet, $this>
     */
    public function sheets(): HasMany
    {
        return $this->hasMany(ProductImportSheet::class)->orderBy('position');
    }

    /**
     * Every staged row of every sheet.
     *
     * @return HasMany<ProductImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class);
    }

    /**
     * The categories, brands, and option values collected for review.
     *
     * @return HasMany<ProductImportValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductImportValue::class);
    }

    /**
     * The image URLs of the imported products.
     *
     * @return HasMany<ProductImportImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImportImage::class);
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ProductImportFactory
    {
        return ProductImportFactory::new();
    }
}
