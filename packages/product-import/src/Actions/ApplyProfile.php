<?php

namespace Lunar\ProductImport\Actions;

use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Mapping\HeaderNormalizer;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Support\LengthUnits;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Support\WeightUnits;

/**
 * Copies a profile's mapping onto a sheet instead of asking the AI. Columns
 * are matched by header and keep their formats, separators, and conventions
 * (code meanings, item parts, and the image size suffix); columns the profile
 * lacks stay unmapped, and targets that no longer exist are dropped and
 * reported.
 */
class ApplyProfile
{
    use GuardsImportStatus;

    public function __construct(
        protected HeaderNormalizer $normalizer,
        protected TargetFieldRegistry $registry,
        protected AnalysisCoordinator $analysis,
        protected WeightUnits $weightUnits,
    ) {}

    /**
     * @return list<string> The names of the targets that were dropped.
     */
    public function handle(ProductImportSheet $sheet, ?ProductImportProfile $profile = null): array
    {
        $import = $sheet->import;

        $this->ensureMappingStep($import);

        $profile ??= $sheet->offeredProfile;

        if (! $profile) {
            throw ImportActionRefused::because('profile_missing');
        }

        $source = SheetMapping::from($profile->mapping);
        $dropped = [];

        $productTypeId = $source->productTypeId;

        if ($productTypeId !== null && ! ProductType::query()->whereKey($productTypeId)->exists()) {
            $dropped[] = __('lunarpanel.product-import::profiles.dropped.product_type');
            $productTypeId = null;
        }

        $byHeader = [];

        foreach ($source->columns as $column) {
            $byHeader[$this->normalizer->normalize($column->header)] ??= $column;
        }

        $existingOptions = ProductOption::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $columns = [];
        $indexByHeader = [];

        foreach ($sheet->columns ?? [] as $profiled) {
            $index = (int) $profiled['index'];
            $header = (string) $profiled['header'];
            $key = $this->normalizer->normalize($header);
            $indexByHeader[$key] ??= $index;

            $copied = $byHeader[$key] ?? null;
            $column = new ColumnMapping($index, $header);

            if ($copied !== null && $copied->target !== null) {
                $column->target = $copied->target;
                $column->locale = $copied->locale;
                $column->optionId = $copied->optionId;
                $column->optionName = $copied->optionName;
                $column->format = $copied->format;
                $column->pair = $copied->pair;

                $drop = $this->droppedTarget($column, $productTypeId, $existingOptions);

                if ($drop !== null) {
                    $dropped[] = $drop;
                    $column->target = null;
                    $column->locale = null;
                    $column->optionId = null;
                    $column->optionName = null;
                    $column->format = null;
                    $column->pair = null;
                }
            }

            $columns[] = $column;
        }

        $mapping = new SheetMapping(
            headerRow: $sheet->header_row,
            productTypeId: $productTypeId,
            grouping: $this->grouping($source, $indexByHeader),
            columns: $columns,
            weightUnit: $this->weightUnits->exists($source->weightUnit) ? $source->weightUnit : null,
            lengthUnit: app(LengthUnits::class)->exists($source->lengthUnit) ? $source->lengthUnit : null,
        );

        if ($mapping->grouping->strategy === GroupingStrategy::ByColumn && ! $mapping->column((int) $mapping->grouping->column)?->is(TargetField::GroupKey)) {
            $mapping->grouping = new Grouping(GroupingStrategy::PerRow);
        }

        $sheet->forceFill([
            'mapping' => $mapping->toArray(),
            'profile_id' => $profile->id,
            'offered_profile_id' => null,
            'offered_profile_score' => null,
            'analysis_status' => SheetAnalysisStatus::ProfileApplied,
            'analysis_error' => null,
        ])->save();

        if ($import->options === null && $profile->options) {
            $import->forceFill(['options' => $profile->options])->save();
        }

        $profile->forceFill([
            'usage_count' => $profile->usage_count + 1,
            'last_used_at' => now(),
        ])->save();

        $this->analysis->settle($import);

        return array_values(array_unique($dropped));
    }

    /**
     * The name of a target the catalog no longer has, or null when it exists.
     *
     * @param  list<int>  $existingOptions
     */
    protected function droppedTarget(ColumnMapping $column, ?int $productTypeId, array $existingOptions): ?string
    {
        if ($column->is(TargetField::VariantOption)) {
            if ($column->optionId !== null && ! in_array($column->optionId, $existingOptions, true)) {
                return __('lunarpanel.product-import::profiles.dropped.option', ['name' => $column->optionName ?: $column->header]);
            }

            return null;
        }

        if (TargetField::isAttribute($column->target) && ! $this->registry->exists((string) $column->target, $productTypeId)) {
            return __('lunarpanel.product-import::profiles.dropped.attribute', ['name' => TargetField::attributeHandle((string) $column->target)]);
        }

        return null;
    }

    /**
     * The profile's grouping, with the group column found by its header.
     *
     * @param  array<string, int>  $indexByHeader
     */
    protected function grouping(SheetMapping $source, array $indexByHeader): Grouping
    {
        if ($source->grouping->strategy !== GroupingStrategy::ByColumn) {
            return new Grouping($source->grouping->strategy);
        }

        $groupColumn = $source->groupKeyColumn();
        $index = $groupColumn ? ($indexByHeader[$this->normalizer->normalize($groupColumn->header)] ?? null) : null;

        return $index === null
            ? new Grouping(GroupingStrategy::PerRow)
            : new Grouping(GroupingStrategy::ByColumn, $index);
    }
}
