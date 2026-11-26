<?php

namespace Lunar\ProductImport\Mapping;

use Lunar\Models\ProductOption;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\InvalidMapping;
use Lunar\ProductImport\Support\LengthUnits;
use Lunar\ProductImport\Support\TargetDefinition;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Support\WeightUnits;

/**
 * Checks a sheet mapping against the mapping rules: a product type, known
 * targets and languages, single-value targets used once, named options,
 * option name and value columns with a pair number, one name and one value
 * per pair, and a value for every name, a group key column for grouping by
 * column, a parent reference column for grouping by parent, a number or date
 * format for every column mapped to a number or date target, a list
 * separator for every list column, a path separator for every category
 * column, and an item separator for every column of packed items, item parts
 * with at most one name and one value, a usable image size suffix, and the
 * store's weight and length units for mapped weights and dimensions.
 */
class MappingValidator
{
    public function __construct(
        protected TargetFieldRegistry $registry,
        protected WeightUnits $weightUnits,
        protected LengthUnits $lengthUnits,
    ) {}

    /**
     * Throw when the mapping breaks a rule.
     *
     * @param  ?string  $sheetName  The sheet's name, for messages that name it.
     *
     * @throws InvalidMapping
     */
    public function validate(SheetMapping $mapping, ?string $sheetName = null): void
    {
        $errors = $this->errors($mapping, $sheetName);

        if ($errors !== []) {
            throw new InvalidMapping($errors);
        }
    }

    /**
     * Every problem of the mapping, as translated messages.
     *
     * @param  ?string  $sheetName  The sheet's name, for messages that name it.
     * @return list<string>
     */
    public function errors(SheetMapping $mapping, ?string $sheetName = null): array
    {
        $errors = [];
        $productTypeId = $mapping->productTypeId;
        $languages = $this->registry->languageCodes();
        $existingOptions = $this->existingOptionIds($mapping);

        if ($productTypeId === null) {
            $errors[] = __('lunarpanel.product-import::mapping.errors.product_type_required');
        }

        $used = [];

        foreach ($mapping->mappedColumns() as $column) {
            $target = (string) $column->target;

            if (! $this->registry->exists($target, $productTypeId)) {
                $errors[] = __('lunarpanel.product-import::mapping.errors.unknown_target', ['column' => $column->header]);

                continue;
            }

            $label = $this->registry->label($target, $productTypeId);
            $format = $this->registry->format($target, $productTypeId);

            if ($format === TargetDefinition::FORMAT_NUMBER && ! $column->format?->hasNumberFormat()) {
                $errors[] = __('lunarpanel.product-import::mapping.errors.number_format_required', ['sheet' => $sheetName ?? '', 'column' => $column->header]);
            }

            if ($format === TargetDefinition::FORMAT_DATE && ! $column->format?->hasDateFormat()) {
                $errors[] = __('lunarpanel.product-import::mapping.errors.date_format_required', ['sheet' => $sheetName ?? '', 'column' => $column->header]);
            }

            foreach ($column->neededSeparators() as $kind) {
                if (! ColumnFormat::isValidSeparator($column->format?->separatorOf($kind))) {
                    $errors[] = __('lunarpanel.product-import::mapping.errors.'.$kind.'_separator_required', ['sheet' => $sheetName ?? '', 'column' => $column->header]);
                }
            }

            $field = TargetField::tryFrom($target);

            if ($field?->takesItemParts() && ($column->format?->itemParts ?? []) !== [] && ! ColumnFormat::isValidItemParts($column->format->itemParts)) {
                $errors[] = __('lunarpanel.product-import::mapping.errors.item_parts_invalid', ['column' => $column->header]);
            }

            if ($field?->takesImageSuffix() && $column->format?->imageSuffix !== null && ! ColumnFormat::isValidImageSuffix($column->format->imageSuffix)) {
                $errors[] = __('lunarpanel.product-import::mapping.errors.image_suffix_invalid', ['column' => $column->header]);
            }

            if ($this->registry->takesLocale($target, $productTypeId)) {
                if ($column->locale === null || $column->locale === '') {
                    $errors[] = __('lunarpanel.product-import::mapping.errors.locale_required', ['column' => $column->header]);

                    continue;
                }

                if (! in_array($column->locale, $languages, true)) {
                    $errors[] = __('lunarpanel.product-import::mapping.errors.unknown_locale', ['column' => $column->header]);

                    continue;
                }

                $key = $target.'.'.$column->locale;
                $duplicate = __('lunarpanel.product-import::mapping.errors.duplicate_target_locale', ['target' => $label, 'locale' => $column->locale]);
            } elseif ($column->is(TargetField::VariantOption)) {
                if ($column->optionId !== null && ! in_array($column->optionId, $existingOptions, true)) {
                    $errors[] = __('lunarpanel.product-import::mapping.errors.unknown_option', ['column' => $column->header]);

                    continue;
                }

                if ($column->optionKey() === null) {
                    $errors[] = __('lunarpanel.product-import::mapping.errors.option_required', ['column' => $column->header]);

                    continue;
                }

                $key = (string) $column->optionKey();
                $duplicate = __('lunarpanel.product-import::mapping.errors.duplicate_option', [
                    'option' => $column->optionName ?: $column->header,
                ]);
            } elseif (TargetField::tryFrom($target)?->isPair()) {
                if ($column->pair === null || $column->pair < 1) {
                    $errors[] = __('lunarpanel.product-import::mapping.errors.pair_required', ['column' => $column->header]);

                    continue;
                }

                $key = $target.'.'.$column->pair;
                $duplicate = __('lunarpanel.product-import::mapping.errors.duplicate_target_pair', ['target' => $label, 'pair' => $column->pair]);
            } elseif (TargetField::isAttribute($target) || TargetField::from($target)->isSingleValue()) {
                $key = $target;
                $duplicate = __('lunarpanel.product-import::mapping.errors.duplicate_target', ['target' => $label]);
            } else {
                continue;
            }

            if (isset($used[$key])) {
                $errors[] = $duplicate;

                continue;
            }

            $used[$key] = true;
        }

        foreach ($mapping->pairs() as $pair => $columns) {
            if ($columns['name'] !== [] && $columns['value'] === []) {
                $errors[] = __('lunarpanel.product-import::mapping.errors.pair_without_value', ['pair' => $pair]);
            }
        }

        if ($mapping->grouping->strategy === GroupingStrategy::ByColumn && $mapping->groupKeyColumn() === null) {
            $errors[] = __('lunarpanel.product-import::mapping.errors.group_column_required');
        }

        if ($mapping->grouping->strategy === GroupingStrategy::ByParent && ! $mapping->maps(TargetField::SourceParent)) {
            $errors[] = __('lunarpanel.product-import::mapping.errors.parent_column_required');
        }

        if (($mapping->maps(TargetField::VariantWeight) || $mapping->maps(TargetField::VariantWeightImpact)) && ! $this->weightUnits->exists($mapping->weightUnit)) {
            $errors[] = __('lunarpanel.product-import::mapping.errors.weight_unit_required', ['sheet' => $sheetName ?? '']);
        }

        if ($mapping->mapsDimensions() && ! $this->lengthUnits->exists($mapping->lengthUnit)) {
            $errors[] = __('lunarpanel.product-import::mapping.errors.length_unit_required', ['sheet' => $sheetName ?? '']);
        }

        return array_values(array_unique($errors));
    }

    /**
     * The ids of the existing options the mapping refers to.
     *
     * @return list<int>
     */
    protected function existingOptionIds(SheetMapping $mapping): array
    {
        $ids = array_values(array_filter(array_map(
            fn ($column): ?int => $column->optionId,
            $mapping->columnsFor(TargetField::VariantOption),
        )));

        if ($ids === []) {
            return [];
        }

        return ProductOption::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
