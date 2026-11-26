<?php

namespace Lunar\ProductImport\Normalizing;

use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueMeaning;
use Lunar\ProductImport\Support\KeyNormalizer;
use Lunar\ProductImport\Support\TargetDefinition;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * Turns a row's raw cells and its sheet's mapping into normalized values.
 * Each cell is first cleaned (`CellCleaner`) and read with its column's
 * conventions (`ColumnConventions`: code meanings, item parts, and the image
 * size suffix). Then come trimmed text, numbers and dates read strictly in
 * their column's format (cells the spreadsheet stores as numbers or dates in
 * their fixed form), prices in the default currency's minor units (a price
 * impact keeps up to four decimals of them, because tax may still multiply
 * it, `RowStructurer`), whole-number stock, weights in the sheet's unit (never converted),
 * statuses, lists split on their column's separators, category paths, and
 * option values. A value that does not read keeps its raw text for the
 * message. The raw cells are never changed.
 */
class RowNormalizer
{
    /**
     * Status cell values, after `KeyNormalizer::key()`, that mean published.
     *
     * @var list<string>
     */
    public const PUBLISHED_VALUES = ['1', 'yes', 'true', 'publish', 'published', 'active', 'aktiv', 'activ', 'igen', 'da'];

    /**
     * Status cell values, after `KeyNormalizer::key()`, that mean draft.
     *
     * @var list<string>
     */
    public const DRAFT_VALUES = ['0', '-1', 'no', 'false', 'draft', 'private', 'pending', 'inactive', 'inaktiv', 'inactiv', 'nem', 'nu'];

    /**
     * Cell values, after `KeyNormalizer::key()`, that mean yes.
     *
     * @var list<string>
     */
    public const TRUE_VALUES = ['1', 'yes', 'y', 'true', 'x', 'on', 'da', 'igen'];

    /**
     * Cell values, after `KeyNormalizer::key()`, that mean no.
     *
     * @var list<string>
     */
    public const FALSE_VALUES = ['0', 'no', 'n', 'false', 'off', 'nu', 'nem'];

    /**
     * Stock status cell values, after `KeyNormalizer::key()`, by the status
     * they mean.
     *
     * @var array<string, list<string>>
     */
    public const STOCK_STATUS_VALUES = [
        'in_stock' => ['instock', 'in stock', 'in_stock', '1', 'yes', 'true', 'available', 'in stoc', 'disponibil', 'da', 'raktaron', 'keszleten', 'igen'],
        'out_of_stock' => ['outofstock', 'out of stock', 'out_of_stock', '0', 'no', 'false', 'unavailable', 'stoc epuizat', 'epuizat', 'indisponibil', 'nu', 'elfogyott', 'nincs raktaron', 'nem'],
        'on_backorder' => ['onbackorder', 'on backorder', 'on_backorder', 'backorder', 'la comanda', 'precomanda', 'rendelesre', 'elorendeles'],
    ];

    public function __construct(
        protected NumberParser $numbers,
        protected DateParser $dates,
        protected CategoryList $categories,
        protected TargetFieldRegistry $registry,
        protected CellCleaner $cleaner,
        protected ColumnConventions $conventions,
    ) {}

    /**
     * @param  array<int|string, ?string>  $raw
     * @param  int  $currencyFactor  The default currency's minor units per unit (100 for two decimals).
     * @param  list<int>  $typed  The column indexes of cells the spreadsheet stores as numbers or dates.
     */
    public function normalize(array $raw, SheetMapping $mapping, int $currencyFactor, array $typed = []): NormalizedRow
    {
        $row = new NormalizedRow;

        foreach ($mapping->columns as $column) {
            $field = $column->fieldPath();

            if ($field === null) {
                continue;
            }

            $cell = $raw[$column->index] ?? $raw[(string) $column->index] ?? null;
            $text = $this->clean($column, $cell === null ? '' : (string) $cell);

            if ($text === '') {
                $row->values[$field] = null;

                continue;
            }

            $this->normalizeCell($row, $mapping, $column, $field, $text, $currencyFactor, in_array($column->index, $typed, true));
        }

        return $row;
    }

    /**
     * Read one value entered for a field the way staging reads its column's
     * cells, for example a staff correction. Null when the sheet maps no
     * column to the field.
     */
    public function normalizeField(SheetMapping $mapping, string $field, string $text, int $currencyFactor): ?NormalizedRow
    {
        $column = $mapping->columnForField($field);

        if ($column === null) {
            return null;
        }

        $row = new NormalizedRow;
        $this->normalizeCell($row, $mapping, $column, $field, $this->clean($column, $text), $currencyFactor, false);

        return $row;
    }

    /**
     * A cell trimmed and cleaned the same way on every sheet.
     */
    protected function clean(ColumnMapping $column, string $text): string
    {
        $text = Str::trim($text);

        return $text === '' ? '' : Str::trim($this->cleaner->clean($column->target, $text));
    }

    /**
     * Normalize one filled cell into the row.
     */
    protected function normalizeCell(
        NormalizedRow $row,
        SheetMapping $mapping,
        ColumnMapping $column,
        string $field,
        string $text,
        int $currencyFactor,
        bool $isTyped,
    ): void {
        $target = TargetField::tryFrom((string) $column->target);
        $format = $this->registry->format((string) $column->target, $mapping->productTypeId);

        if ($format === TargetDefinition::FORMAT_NUMBER) {
            $this->normalizeNumber($row, $target, $field, $text, $isTyped ? ColumnFormat::fixedNumber() : $column->format, $currencyFactor);

            return;
        }

        if ($format === TargetDefinition::FORMAT_DATE) {
            // An end without a time lasts until the end of its day.
            $endOfDay = $target === TargetField::VariantSaleEnds;
            $date = $isTyped ? $this->dates->parseTyped($text, $endOfDay) : $this->dates->parse($text, $column->format?->dateFormat, $endOfDay);

            $date === null
                ? $this->invalid($row, $field, $text)
                : $row->values[$field] = $date;

            return;
        }

        $meaning = $this->conventions->meaning($column, $text);

        if ($meaning === ValueMeaning::Empty) {
            $row->values[$field] = null;

            return;
        }

        switch ($target) {
            case TargetField::VariantEan:
            case TargetField::VariantGtin:
                $row->values[$field] = (string) Str::replaceMatches('/\s+/u', '', $text);

                return;

            case TargetField::VariantStockStatus:
                $status = $meaning?->value ?? $this->stockStatus($text);

                $status === null
                    ? $this->invalid($row, $field, $text)
                    : $row->values[$field] = $status;

                return;

            case TargetField::VariantBackorders:
            case TargetField::VariantShippable:
                $boolean = $meaning !== null ? $meaning === ValueMeaning::Yes : $this->boolean($text);

                $boolean === null
                    ? $this->invalid($row, $field, $text)
                    : $row->values[$field] = $boolean;

                return;

            case TargetField::ProductTags:
            case TargetField::ProductUpsells:
            case TargetField::ProductCrossSells:
                $row->values[$field] = ListItems::distinct($text, $column->listSeparator()) ?: null;

                return;

            case TargetField::ProductImages:
            case TargetField::VariantImage:
                $row->values[$field] = $this->conventions->images($column, $text) ?: null;

                return;

            case TargetField::ProductProperties:
            case TargetField::VariantOptions:
                $row->values[$field] = $this->conventions->namedItems($column, $text) ?: null;

                return;

            case TargetField::VariantOptionName:
            case TargetField::VariantOptionValue:
                $items = $this->conventions->pairItems($column, $text);
                $row->values[$field] = array_filter($items, fn (string $item): bool => $item !== '') === [] ? null : $items;

                return;

            case TargetField::SourceId:
            case TargetField::SourceParent:
            case TargetField::SourceType:
                $row->values[$field] = Str::squish($text);

                return;

            case TargetField::ProductStatus:
                $status = $meaning?->value ?? $this->status($text);

                $status === null
                    ? $this->invalid($row, $field, $text)
                    : $row->values[$field] = $status;

                return;

            case TargetField::ProductCategory:
                $row->values[$field] = $this->categories->parse($text, $column);

                return;

            case TargetField::ProductSlug:
                // Lunar's slugs, in the column's language, short enough for a number suffix.
                $slug = Str::rtrim(Str::limit(Str::slug($text, '-', $column->locale), 200, ''), '-');

                $slug === ''
                    ? $this->invalid($row, $field, $text)
                    : $row->values[$field] = $slug;

                return;

            case TargetField::ProductBrand:
            case TargetField::VariantOption:
                $row->values[$field] = Str::squish($text);

                return;

            default:
                $row->values[$field] = $text;
        }
    }

    /**
     * Read a number in its column's format: money in minor units, whole
     * numbers as integers, and other numbers as floats.
     */
    protected function normalizeNumber(NormalizedRow $row, ?TargetField $target, string $field, string $text, ?ColumnFormat $format, int $currencyFactor): void
    {
        $number = $this->numbers->parse($text, $format);

        if ($number === null || ($target?->isWholeNumber() && ! $this->isWhole($number))) {
            $this->invalid($row, $field, $text);

            return;
        }

        $row->values[$field] = match (true) {
            $target === TargetField::VariantPriceImpact => $this->preciseMinorUnits($number, $currencyFactor),
            (bool) $target?->isMoney() => (int) bcmul($number, (string) $currencyFactor, 0),
            (bool) $target?->isWholeNumber() => (int) $number,
            default => round((float) $number, 4),
        };
    }

    /**
     * An amount in minor units with up to four decimals of them: an integer
     * when it is whole, otherwise a float, such as 826.4463 for 8.264463.
     */
    protected function preciseMinorUnits(string $number, int $currencyFactor): int|float
    {
        $minor = bcmul($number, (string) $currencyFactor, 4);
        $whole = bcadd($minor, '0', 0);

        return bccomp($minor, $whole, 4) === 0 ? (int) $whole : (float) $minor;
    }

    /**
     * Keep an unreadable cell's raw text for the validation message.
     */
    protected function invalid(NormalizedRow $row, string $field, string $text): void
    {
        $row->values[$field] = null;
        $row->invalid[$field] = $text;
    }

    /**
     * Whether a decimal string has no fraction.
     */
    protected function isWhole(string $number): bool
    {
        return ! Str::contains($number, '.') || (float) $number === floor((float) $number);
    }

    /**
     * The stock status a cell means: `in_stock`, `out_of_stock`, or
     * `on_backorder`, or null when the value is not recognized.
     */
    public function stockStatus(string $text): ?string
    {
        $key = KeyNormalizer::key($text);

        foreach (self::STOCK_STATUS_VALUES as $status => $values) {
            if (in_array($key, $values, true)) {
                return $status;
            }
        }

        return null;
    }

    /**
     * The yes or no a cell means, or null when the value is not recognized.
     */
    public function boolean(string $text): ?bool
    {
        $key = KeyNormalizer::key($text);

        return match (true) {
            in_array($key, self::TRUE_VALUES, true) => true,
            in_array($key, self::FALSE_VALUES, true) => false,
            default => null,
        };
    }

    /**
     * The status a status cell means, `published` or `draft`, or null when
     * the value is not recognized.
     */
    public function status(string $text): ?string
    {
        $key = KeyNormalizer::key($text);

        return match (true) {
            in_array($key, self::PUBLISHED_VALUES, true) => 'published',
            in_array($key, self::DRAFT_VALUES, true) => 'draft',
            default => null,
        };
    }
}
