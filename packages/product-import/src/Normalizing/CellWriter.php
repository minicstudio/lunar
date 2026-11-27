<?php

namespace Lunar\ProductImport\Normalizing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Enums\ItemPart;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueMeaning;
use Throwable;

/**
 * Writes a normalized value back as cell text that its column reads the same
 * way, for the row correction form: money in major units and numbers with
 * the column's decimal separator, dates in the column's date format, lists
 * with the column's separators, and codes as the column's own code for
 * their meaning. Saving the form unchanged then stores the same values.
 */
class CellWriter
{
    public function __construct(protected CategoryList $categories) {}

    /**
     * The text of a normalized value of the column.
     *
     * @param  int  $currencyFactor  The default currency's minor units per unit (100 for two decimals).
     */
    public function write(ColumnMapping $column, mixed $value, int $currencyFactor): string
    {
        $target = TargetField::tryFrom((string) $column->target);

        return match (true) {
            $value === null => '',
            $target === TargetField::ProductCategory => $this->categories->format(CategoryList::paths($value), $column),
            (bool) $target?->isPackedItems() => $this->namedItems($column, (array) $value),
            (bool) $target?->isPair() => $this->pairItems($column, (array) $value),
            is_array($value) => $this->list($column, $value),
            (bool) $target?->isMoney() && is_int($value) => $this->number($column, $value / max(1, $currencyFactor), (int) round(log10(max(1, $currencyFactor)))),
            (bool) $target?->isMoney() && is_float($value) => $this->number($column, $value / max(1, $currencyFactor), maxDecimals: (int) round(log10(max(1, $currencyFactor))) + 4),
            is_int($value) || is_float($value) => $this->number($column, $value),
            is_bool($value) => $this->code($column, $value ? ValueMeaning::Yes : ValueMeaning::No),
            (bool) $target?->isDate() => $this->date($column, (string) $value),
            in_array($target, [TargetField::ProductStatus, TargetField::VariantStockStatus], true) => $this->code($column, ValueMeaning::tryFrom((string) $value)) ?? (string) $value,
            default => (string) $value,
        };
    }

    /**
     * A number with the column's decimal separator and no thousands
     * separator, with the given decimals or as many as it has, up to
     * `$maxDecimals`.
     */
    protected function number(ColumnMapping $column, int|float $number, ?int $decimals = null, int $maxDecimals = 4): string
    {
        $text = $decimals !== null
            ? number_format($number, $decimals, '.', '')
            : (is_int($number) ? (string) $number : Str::rtrim(Str::rtrim(number_format($number, $maxDecimals, '.', ''), '0'), '.'));

        return Str::replace('.', $column->format?->decimalSeparator ?? '.', $text);
    }

    /**
     * A stored date in the column's date format.
     */
    protected function date(ColumnMapping $column, string $value): string
    {
        $format = $column->format?->dateFormat;

        if ($format === null) {
            return $value;
        }

        try {
            return CarbonImmutable::createFromFormat(DateParser::STORED_FORMAT, $value)->format($format);
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * The column's code for a meaning, or the meaning's own word, which the
     * built-in words read.
     */
    protected function code(ColumnMapping $column, ?ValueMeaning $meaning): ?string
    {
        if ($meaning === null) {
            return null;
        }

        $code = array_search($meaning->value, $column->format?->values ?? [], true);

        return $code === false ? $meaning->value : (string) $code;
    }

    /**
     * List items joined with the column's list separator, a separator inside
     * an item escaped with a backslash.
     *
     * @param  array<int|string, mixed>  $items
     */
    protected function list(ColumnMapping $column, array $items): string
    {
        $separator = $column->listSeparator();

        return implode($separator === '' ? ' ' : $separator.' ', array_map(
            fn (mixed $item): string => $separator === '' ? (string) $item : Str::replace($separator, '\\'.$separator, (string) $item),
            array_values($items),
        ));
    }

    /**
     * `Name:Value` items with the column's item separator, in the order its
     * item parts give.
     *
     * @param  array<int|string, mixed>  $items
     */
    protected function namedItems(ColumnMapping $column, array $items): string
    {
        $parts = $column->format?->parts() ?: [ItemPart::Name, ItemPart::Value];

        return $this->list($column, array_map(fn (mixed $item): string => $this->packed($column, $parts, [
            ItemPart::Name->value => (string) ($item['name'] ?? ''),
            ItemPart::Value->value => (string) ($item['value'] ?? ''),
        ]), array_filter($items, 'is_array')));
    }

    /**
     * The items of an option name or value column, each in the part its item
     * parts give it.
     *
     * @param  array<int|string, mixed>  $items
     */
    protected function pairItems(ColumnMapping $column, array $items): string
    {
        $parts = $column->format?->parts() ?? [];
        $wanted = $column->is(TargetField::VariantOptionName) ? ItemPart::Name : ItemPart::Value;

        return $this->list($column, array_map(
            fn (mixed $item): string => $parts === [] ? (string) $item : $this->packed($column, $parts, [$wanted->value => (string) $item]),
            $items,
        ));
    }

    /**
     * One packed item: the given pieces at their parts' positions, joined
     * with the column's item separator, without trailing empty parts.
     *
     * @param  list<ItemPart>  $parts
     * @param  array<string, string>  $pieces  Part => text.
     */
    protected function packed(ColumnMapping $column, array $parts, array $pieces): string
    {
        $texts = array_map(fn (ItemPart $part): string => $pieces[$part->value] ?? '', $parts);

        while ($texts !== [] && end($texts) === '') {
            array_pop($texts);
        }

        return implode($column->itemSeparator(), $texts);
    }
}
