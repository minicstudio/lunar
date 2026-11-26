<?php

namespace Lunar\ProductImport\Analysis;

use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueMeaning;
use Lunar\ProductImport\Exceptions\ProposalRejected;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Normalizing\DateParser;
use Lunar\ProductImport\Normalizing\NumberParser;
use Lunar\ProductImport\Support\KeyNormalizer;
use Lunar\ProductImport\Support\LengthUnits;
use Lunar\ProductImport\Support\MeasurementUnits;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Support\WeightUnits;

/**
 * Checks an AI answer and turns it into the sheet's prefilled mapping.
 *
 * The answer is rejected when it names a column that does not exist, uses a
 * target or language outside the allowed lists, gives one single-value
 * target to two columns, groups by a missing column, or maps neither a
 * product name nor a SKU. A target is selected when its confidence reaches
 * the preselect threshold, and highlighted while it stays below the review
 * threshold; a lower suggestion is kept but left unmapped. Each column keeps
 * its number or date format, unless the format is unusable, and a column
 * whose text samples do not all read in its format is highlighted. Each
 * column also keeps the separators its target uses and its conventions:
 * code meanings keyed by `KeyNormalizer`, item parts, and an image size
 * suffix. An unusable format or separator, a meaning that does not fit the
 * column's target, item parts with more than one name or value, and an
 * unusable suffix are dropped and the column is highlighted. The sheet's
 * weight and length units are kept when they are among the store's units.
 * The proposal never changes the import options.
 */
class ProposalValidator
{
    public function __construct(
        protected TargetFieldRegistry $registry,
        protected WeightUnits $weightUnits,
        protected LengthUnits $lengthUnits,
        protected NumberParser $numbers,
        protected DateParser $dates,
    ) {}

    /**
     * @param  array<string, mixed>  $answer
     *
     * @throws ProposalRejected
     */
    public function toMapping(array $answer, ProductImportSheet $sheet, ?int $productTypeId): SheetMapping
    {
        $headers = collect($sheet->columns ?? [])->mapWithKeys(fn (array $column): array => [(int) $column['index'] => (string) $column['header']]);
        $profiles = collect($sheet->columns ?? [])->keyBy(fn (array $column): int => (int) $column['index']);
        $languages = $this->registry->languageCodes();
        $defaultLanguage = $this->registry->defaultLanguage();
        $preselect = (float) config('lunar.product-import.ai.confidence.preselect', 0.60);
        $review = (float) config('lunar.product-import.ai.confidence.review', 0.85);
        $options = $this->existingOptionsByKey();

        if (! is_array($answer['columns'] ?? null)) {
            throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.no_columns'));
        }

        $suggestions = [];
        $used = [];

        foreach ($answer['columns'] as $entry) {
            if (! is_array($entry) || ! is_numeric($entry['index'] ?? null)) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.no_columns'));
            }

            $index = (int) $entry['index'];
            $target = (string) ($entry['target'] ?? '');
            $locale = isset($entry['locale']) && $entry['locale'] !== '' ? (string) $entry['locale'] : null;

            if (! $headers->has($index)) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.unknown_column', ['index' => $index]));
            }

            if (! $this->registry->exists($target, $productTypeId)) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.unknown_target', ['target' => $target]));
            }

            if ($locale !== null && ! in_array($locale, $languages, true)) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.unknown_language', ['locale' => $locale]));
            }

            $takesLocale = $this->registry->takesLocale($target, $productTypeId);
            $locale = $takesLocale ? ($locale ?? $defaultLanguage) : null;
            $pair = is_numeric($entry['pair'] ?? null) && (int) $entry['pair'] >= 1 ? (int) $entry['pair'] : null;

            if (TargetField::tryFrom($target)?->isPair() && $pair === null) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.missing_pair', ['column' => $headers->get($index)]));
            }

            $usageKey = $this->usageKey($target, $locale, $pair);

            if ($usageKey !== null) {
                if (isset($used[$usageKey])) {
                    throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.duplicate_target', [
                        'target' => $this->registry->label($target, $productTypeId),
                    ]));
                }

                $used[$usageKey] = true;
            }

            $dropped = false;

            $suggestions[$index] = [
                'target' => $target,
                'locale' => $locale,
                'confidence' => max(0.0, min(1.0, (float) ($entry['confidence'] ?? 0))),
                'option_name' => isset($entry['option_name']) && Str::trim((string) $entry['option_name']) !== '' ? Str::trim((string) $entry['option_name']) : null,
                'format' => $this->format($entry, TargetField::tryFrom($target), $dropped),
                'dropped' => $dropped,
                'pair' => TargetField::tryFrom($target)?->isPair() ? $pair : null,
            ];
        }

        foreach ($used as $usageKey => $true) {
            if (Str::startsWith($usageKey, TargetField::VariantOptionName->value.'.')
                && ! isset($used[TargetField::VariantOptionValue->value.'.'.Str::afterLast($usageKey, '.')])) {
                throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.pair_without_value', ['pair' => Str::afterLast($usageKey, '.')]));
            }
        }

        $grouping = $this->grouping($answer['grouping'] ?? null, $headers->keys()->all());

        if ($grouping->strategy === GroupingStrategy::ByParent && ! isset($used[TargetField::SourceParent->value])) {
            throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.missing_parent_column'));
        }

        $columns = [];

        foreach ($headers as $index => $header) {
            $suggestion = $suggestions[$index] ?? null;

            if ($suggestion === null) {
                $columns[] = new ColumnMapping($index, $header);

                continue;
            }

            $selected = $suggestion['confidence'] >= $preselect;
            $isOption = $suggestion['target'] === TargetField::VariantOption->value;
            $format = $suggestion['format'];
            $samplesFit = $format === null || $this->samplesFit($format, $profiles->get($index) ?? []);
            $optionName = $suggestion['option_name'] ?? $header;
            $optionId = $isOption ? ($options[KeyNormalizer::key($optionName)] ?? null) : null;

            $columns[] = new ColumnMapping(
                index: $index,
                header: $header,
                target: $selected ? $suggestion['target'] : null,
                locale: $suggestion['locale'],
                optionId: $optionId,
                optionName: $isOption && $optionId === null ? $optionName : null,
                confidence: $suggestion['confidence'],
                suggestedTarget: $suggestion['target'],
                flagged: ($selected && $suggestion['confidence'] < $review) || ! $samplesFit || $suggestion['dropped'],
                format: $format,
                pair: $suggestion['pair'],
            );
        }

        $units = is_array($answer['units'] ?? null) ? $answer['units'] : [];

        $mapping = new SheetMapping(
            headerRow: $sheet->header_row,
            productTypeId: $productTypeId,
            grouping: $grouping,
            columns: $columns,
            weightUnit: $this->unit($units['weight_unit'] ?? null, $this->weightUnits),
            lengthUnit: $this->unit($units['length_unit'] ?? null, $this->lengthUnits),
        );

        $this->applyGroupColumn($mapping, $preselect, $review);

        if (! isset($used[TargetField::VariantSku->value]) && ! $this->suggestsName($suggestions)) {
            throw ProposalRejected::noProductData();
        }

        return $mapping;
    }

    /**
     * The key a target may be used once under: single-value targets once per
     * sheet, name, description, and attributes once per language, and option
     * names and values once per pair number.
     */
    protected function usageKey(string $target, ?string $locale, ?int $pair = null): ?string
    {
        if (TargetField::isAttribute($target)) {
            return $target.'.'.($locale ?? '');
        }

        $field = TargetField::from($target);

        return match (true) {
            $field->isSingleValue() => $target,
            $field->takesLocale() => $target.'.'.$locale,
            $field->isPair() => $target.'.'.$pair,
            default => null,
        };
    }

    /**
     * Read the grouping suggestion.
     *
     * @param  list<int>  $columnIndexes
     *
     * @throws ProposalRejected
     */
    protected function grouping(mixed $grouping, array $columnIndexes): Grouping
    {
        if (! is_array($grouping)) {
            return new Grouping;
        }

        $strategy = GroupingStrategy::tryFrom((string) ($grouping['strategy'] ?? ''));

        if ($strategy === null) {
            throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.unknown_grouping'));
        }

        $column = is_numeric($grouping['column'] ?? null) ? (int) $grouping['column'] : null;

        if ($strategy === GroupingStrategy::ByColumn && ($column === null || ! in_array($column, $columnIndexes, true))) {
            throw ProposalRejected::invalid(__('lunarpanel.product-import::analysis.reasons.missing_group_column'));
        }

        return new Grouping(
            $strategy,
            $strategy === GroupingStrategy::ByColumn ? $column : null,
            max(0.0, min(1.0, (float) ($grouping['confidence'] ?? 0))),
        );
    }

    /**
     * Make the grouping column hold the group key. When it ends up unmapped,
     * every row is its own product until staff decide.
     */
    protected function applyGroupColumn(SheetMapping $mapping, float $preselect, float $review): void
    {
        if ($mapping->grouping->strategy !== GroupingStrategy::ByColumn) {
            return;
        }

        $column = $mapping->column((int) $mapping->grouping->column);
        $confidence = $mapping->grouping->confidence ?? 0.0;

        if ($column !== null && $column->target === null && $column->suggestedTarget !== TargetField::GroupKey->value && $confidence >= $preselect) {
            $column->target = TargetField::GroupKey->value;
            $column->suggestedTarget = TargetField::GroupKey->value;
            $column->confidence = $confidence;
            $column->flagged = $confidence < $review;
        }

        if ($column === null || ! $column->is(TargetField::GroupKey)) {
            $mapping->grouping = new Grouping(GroupingStrategy::PerRow, null, $mapping->grouping->confidence);
        }
    }

    /**
     * Whether any column was suggested as the product name.
     *
     * @param  array<int, array{target: string}>  $suggestions
     */
    protected function suggestsName(array $suggestions): bool
    {
        foreach ($suggestions as $suggestion) {
            if ($suggestion['target'] === TargetField::ProductName->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The column's format from the answer: its number or date format, the
     * separators its target uses, and its conventions. A number format whose
     * separators are the same, a date format with anything but date format
     * characters and separators, a separator that is not `none` or one to
     * three characters without letters or digits, a code meaning that does
     * not fit the target, item parts with more than one name or value, and an
     * image suffix with other characters than letters, digits, "-", and "_" or
     * over 50 characters are dropped, and `$dropped` is set so the column is
     * highlighted. Separators, item parts, and an image suffix are kept only
     * for targets that use them.
     *
     * @param  array<string, mixed>  $entry
     */
    protected function format(array $entry, ?TargetField $target, bool &$dropped): ?ColumnFormat
    {
        $number = is_array($entry['number_format'] ?? null) ? $entry['number_format'] : null;
        $decimal = $number !== null ? (string) ($number['decimal_separator'] ?? '') : null;
        $thousands = $number !== null ? match ((string) ($number['thousands_separator'] ?? '')) {
            'none', '' => '',
            'space' => ' ',
            default => (string) $number['thousands_separator'],
        } : null;

        $numberFormat = $decimal !== null ? ColumnFormat::number($decimal, (string) $thousands) : null;

        if ($numberFormat !== null && ! $numberFormat->hasNumberFormat()) {
            $numberFormat = null;
            $dropped = true;
        }

        $dateFormat = isset($entry['date_format']) && is_string($entry['date_format']) ? Str::trim($entry['date_format']) : null;

        if ($dateFormat !== null && $dateFormat !== '' && ! ColumnFormat::isValidDateFormat($dateFormat)) {
            $dropped = true;
        }

        $dateFormat = ColumnFormat::isValidDateFormat($dateFormat) ? $dateFormat : null;
        $itemParts = $this->itemParts($entry['item_parts'] ?? null, $target, $dropped);
        $separators = $this->separators($entry, $target?->separators($itemParts !== []) ?? [], $dropped);

        $format = new ColumnFormat(
            $numberFormat?->decimalSeparator,
            $numberFormat?->thousandsSeparator,
            $dateFormat,
            $this->meanings($entry['values'] ?? null, $target, $dropped),
            $itemParts,
            $this->imageSuffix($entry['image_suffix'] ?? null, $target, $dropped),
            $separators['list'] ?? null,
            $separators['path'] ?? null,
            $separators['item'] ?? null,
        );

        return $format->isEmpty() ? null : $format;
    }

    /**
     * The separators of the answer that the target uses, by kind (`list`,
     * `path`, `item`). A separator that is not `none` or one to three
     * characters without letters or digits is dropped.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $kinds  The separators the target uses.
     * @return array<string, ?string>
     */
    protected function separators(array $entry, array $kinds, bool &$dropped): array
    {
        $separators = [];

        foreach ($kinds as $kind) {
            $given = $entry[$kind.'_separator'] ?? null;

            if (! is_string($given) || $given === '') {
                continue;
            }

            $separators[$kind] = ColumnFormat::separator($given);
            $dropped = $dropped || $separators[$kind] === null;
        }

        return $separators;
    }

    /**
     * The code meanings of the answer that fit the target, keyed by each
     * code's `KeyNormalizer` key.
     *
     * @return array<string, string>
     */
    protected function meanings(mixed $values, ?TargetField $target, bool &$dropped): array
    {
        $meanings = [];

        foreach (is_array($values) ? $values : [] as $entry) {
            $code = is_array($entry) && is_scalar($entry['value'] ?? null) ? KeyNormalizer::key((string) $entry['value']) : '';
            $meaning = is_array($entry) ? ValueMeaning::tryFrom((string) ($entry['meaning'] ?? '')) : null;

            if ($code === '' || $meaning === null || ! $meaning->fits($target)) {
                $dropped = true;

                continue;
            }

            $meanings[$code] = $meaning->value;
        }

        return $meanings;
    }

    /**
     * The item parts of the answer, when the target reads packed items and
     * they name at most one name and one value.
     *
     * @return list<string>
     */
    protected function itemParts(mixed $parts, ?TargetField $target, bool &$dropped): array
    {
        if (! is_array($parts) || $parts === [] || ! $target?->takesItemParts()) {
            return [];
        }

        $parts = array_values(array_map(fn (mixed $part): string => Str::lower(Str::trim((string) $part)), $parts));

        if (! ColumnFormat::isValidItemParts($parts)) {
            $dropped = true;

            return [];
        }

        return $parts;
    }

    /**
     * The image size suffix of the answer, when the target holds image URLs
     * and the suffix is usable.
     */
    protected function imageSuffix(mixed $suffix, ?TargetField $target, bool &$dropped): ?string
    {
        $suffix = is_string($suffix) ? Str::trim($suffix) : '';

        if ($suffix === '' || ! $target?->takesImageSuffix()) {
            return null;
        }

        if (! ColumnFormat::isValidImageSuffix($suffix)) {
            $dropped = true;

            return null;
        }

        return $suffix;
    }

    /**
     * Whether every text sample of the column reads in its format. Samples
     * taken from cells the spreadsheet stores as numbers or dates are read in
     * their fixed form, so they are not checked.
     *
     * @param  array<string, mixed>  $profile
     */
    protected function samplesFit(ColumnFormat $format, array $profile): bool
    {
        if ($profile['samples_typed'] ?? false) {
            return true;
        }

        foreach ($profile['samples'] ?? [] as $sample) {
            $sample = (string) $sample;

            if ($format->hasNumberFormat() && $this->numbers->parse($sample, $format) === null) {
                return false;
            }

            if (! $format->hasNumberFormat() && $format->hasDateFormat() && $this->dates->parse($sample, $format->dateFormat) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * A unit of the answer when it is one of the store's unit codes, else
     * null. Other spellings are not translated.
     */
    protected function unit(mixed $unit, MeasurementUnits $units): ?string
    {
        $unit = is_string($unit) ? Str::trim($unit) : null;

        return $units->exists($unit) ? $unit : null;
    }

    /**
     * Existing option ids by the normalized name in any language.
     *
     * @return array<string, int>
     */
    protected function existingOptionsByKey(): array
    {
        $byKey = [];

        foreach ($this->registry->options() as $id => $name) {
            $byKey[KeyNormalizer::key($name)] ??= $id;
        }

        return $byKey;
    }
}
