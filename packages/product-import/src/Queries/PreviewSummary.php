<?php

namespace Lunar\ProductImport\Queries;

use Illuminate\Database\Eloquent\Builder;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;

/**
 * The preview totals and filters: products, new and updated products,
 * variants, warnings, and errors.
 */
class PreviewSummary
{
    public const FILTER_ALL = 'all';

    public const FILTER_NEW = 'new';

    public const FILTER_UPDATE = 'update';

    public const FILTER_PROBLEMS = 'problems';

    public const FILTER_SKIPPED = 'skipped';

    /**
     * @return array{products: int, new: int, updated: int, variants: int, warnings: int, errors: int, skipped: int, importable: int}
     */
    public function totals(ProductImport $import): array
    {
        $active = fn (): Builder => ProductImportRow::includedIn($import)->where('status', '!=', RowStatus::Skipped);

        $groups = $active()
            ->selectRaw('group_key, max(matched_product_id) as product_id')
            ->groupBy('group_key')
            ->get();

        $statuses = ProductImportRow::includedIn($import)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total);

        $count = fn (RowStatus $status): int => $statuses[$status->value] ?? 0;
        $variantRows = fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
            ->whereNull('role')
            ->orWhere('role', '!=', RowRole::Product->value));

        return [
            'products' => $groups->count(),
            'new' => $groups->whereNull('product_id')->count(),
            'updated' => $groups->whereNotNull('product_id')->count(),
            'variants' => $variantRows($active())->count(),
            'warnings' => $count(RowStatus::Warning),
            'errors' => $count(RowStatus::Error),
            'skipped' => $count(RowStatus::Skipped),
            'importable' => $variantRows(ProductImportRow::includedIn($import)->whereIn('status', [RowStatus::Valid, RowStatus::Warning]))->count(),
        ];
    }

    /**
     * The rows shown for a filter.
     *
     * @return Builder<ProductImportRow>
     */
    public function rows(ProductImport $import, string $filter = self::FILTER_ALL): Builder
    {
        $query = ProductImportRow::includedIn($import);

        return match ($filter) {
            self::FILTER_NEW => $query->whereIn('group_key', $this->groupsWithProduct($import, false))->where('status', '!=', RowStatus::Skipped),
            self::FILTER_UPDATE => $query->whereIn('group_key', $this->groupsWithProduct($import, true))->where('status', '!=', RowStatus::Skipped),
            self::FILTER_PROBLEMS => $query->whereIn('status', [RowStatus::Error, RowStatus::Warning]),
            self::FILTER_SKIPPED => $query->where('status', RowStatus::Skipped),
            default => $query,
        };
    }

    /**
     * The group keys that update an existing product, or that create one.
     *
     * @return Builder<ProductImportRow>
     */
    protected function groupsWithProduct(ProductImport $import, bool $existing): Builder
    {
        return ProductImportRow::includedIn($import)
            ->select('group_key')
            ->groupBy('group_key')
            ->when(
                $existing,
                fn (Builder $query) => $query->havingRaw('max(matched_product_id) is not null'),
                fn (Builder $query) => $query->havingRaw('max(matched_product_id) is null'),
            );
    }
}
