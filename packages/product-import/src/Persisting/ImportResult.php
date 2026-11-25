<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Discountable;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;

/**
 * Counts an import's outcome from its rows and images: new and updated
 * products and variants, skipped and failed rows, created records, variants
 * added to discounts, added associations, downloaded, existing, and failed images, and processed
 * groups and images. Failed images never change the status.
 */
class ImportResult
{
    /**
     * @return array<string, mixed>
     */
    public function stats(ProductImport $import): array
    {
        $rows = fn (): Builder => ProductImportRow::includedIn($import);
        $imported = fn (): Builder => $rows()->where('status', RowStatus::Imported);

        $created = $import->stats['created'] ?? [];

        return [
            ...($import->stats ?? []),
            'new_products' => $imported()->whereNull('matched_product_id')->distinct()->count('product_id'),
            'updated_products' => $imported()->whereNotNull('matched_product_id')->distinct()->count('product_id'),
            'new_variants' => $imported()->whereNotNull('product_variant_id')->where('action', RowAction::Create)->count(),
            'updated_variants' => $imported()->whereNotNull('product_variant_id')->where('action', RowAction::Update)->count(),
            'imported_rows' => $imported()->count(),
            'skipped_rows' => $rows()->where('status', RowStatus::Skipped)->count(),
            'failed_rows' => $rows()->where('status', RowStatus::Failed)->count(),
            'created_brands' => count($created['brands'] ?? []),
            'created_categories' => count($created['categories'] ?? []),
            'created_options' => count($created['options'] ?? []),
            'created_option_values' => count($created['option_values'] ?? []),
            'created_discounts' => count($created['discounts'] ?? []),
            'discount_variants' => $this->variantsAddedToDiscounts($import),
            'processed_groups' => $this->processedGroups($import),
            'total_groups' => (int) ($import->stats['total_groups'] ?? 0),
            'associations' => (int) ($import->stats['associations'] ?? 0),
            'images_downloaded' => $this->images($import, ImageStatus::Downloaded),
            'images_existing' => $this->images($import, ImageStatus::Existing),
            'images_failed' => $this->images($import, ImageStatus::Failed),
            'processed_images' => $this->processedImages($import),
            'total_images' => $this->totalImages($import),
        ];
    }

    /**
     * The imported variants that this import added to their discounts: the
     * links to the discounts its values use or created, made since it
     * started. A link that an earlier import made is not counted again.
     */
    public function variantsAddedToDiscounts(ProductImport $import): int
    {
        $discountIds = $import->values()
            ->where('type', ValueType::Discount)
            ->whereIn('action', [ValueAction::UseExisting, ValueAction::Create])
            ->whereNotNull('target_id')
            ->pluck('target_id')
            ->unique()
            ->values()
            ->all();

        if ($discountIds === [] || $import->started_at === null) {
            return 0;
        }

        $variantIds = ProductImportRow::includedIn($import)
            ->where('status', RowStatus::Imported)
            ->whereNotNull('product_variant_id')
            ->select('product_variant_id');

        return Discountable::query()
            ->whereIn('discount_id', $discountIds)
            ->where('type', 'limitation')
            ->where('discountable_type', ProductVariant::morphName())
            ->whereIn('discountable_id', $variantIds)
            ->where('created_at', '>=', $import->started_at)
            ->distinct()
            ->count('discountable_id');
    }

    /**
     * How far a running import got: the product groups written, then, once
     * every group is written, the images downloaded.
     *
     * @return array{phase: string, processed: int, total: int, percent: int}
     */
    public function progress(ProductImport $import): array
    {
        $total = (int) ($import->stats['total_groups'] ?? 0);
        $processed = $this->processedGroups($import);
        $phase = 'groups';

        if ($processed >= $total && ($images = $this->totalImages($import)) > 0) {
            $phase = 'images';
            $total = $images;
            $processed = $this->processedImages($import);
        }

        return [
            'phase' => $phase,
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? (int) floor(min($processed, $total) / $total * 100) : 0,
        ];
    }

    /**
     * The import's images with the given status.
     */
    public function images(ProductImport $import, ImageStatus $status): int
    {
        return ProductImportImage::query()->where('product_import_id', $import->id)->where('status', $status)->count();
    }

    /**
     * The images already downloaded, found, or failed, for the progress.
     */
    public function processedImages(ProductImport $import): int
    {
        return ProductImportImage::query()->where('product_import_id', $import->id)->where('status', '!=', ImageStatus::Pending)->count();
    }

    /**
     * Every image the import recorded.
     */
    public function totalImages(ProductImport $import): int
    {
        return ProductImportImage::query()->where('product_import_id', $import->id)->count();
    }

    /**
     * The groups already written or failed, for the progress while importing.
     */
    public function processedGroups(ProductImport $import): int
    {
        return ProductImportRow::includedIn($import)
            ->whereIn('status', [RowStatus::Imported, RowStatus::Failed])
            ->distinct()
            ->count('group_key');
    }

    /**
     * The final status: completed, completed with errors when rows failed,
     * failed when nothing was imported.
     *
     * @param  array<string, mixed>  $stats
     */
    public function status(array $stats): ImportStatus
    {
        return match (true) {
            $stats['imported_rows'] === 0 => ImportStatus::Failed,
            $stats['failed_rows'] > 0 => ImportStatus::CompletedWithErrors,
            default => ImportStatus::Completed,
        };
    }

    /**
     * The products the import wrote.
     *
     * @return Builder<ProductImportRow>
     */
    public function touchedProducts(ProductImport $import): Builder
    {
        return ProductImportRow::includedIn($import)
            ->where('status', RowStatus::Imported)
            ->whereNotNull('product_id')
            ->select('product_id')
            ->distinct();
    }
}
