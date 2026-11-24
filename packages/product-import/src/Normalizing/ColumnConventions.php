<?php

namespace Lunar\ProductImport\Normalizing;

use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Enums\ItemPart;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueMeaning;

/**
 * Applies the conventions a column's format describes, after the cell is
 * cleaned and before it is read: the meaning of its codes, the separators
 * and parts of its packed list items, and the size suffix of its image URLs.
 * A column without conventions is read with the general rules: no code has a
 * meaning, a `Name:Value` item splits on its first item separator, and URLs
 * stay as they are.
 */
class ColumnConventions
{
    /**
     * The meaning the column gives a cell's code, or null when it gives none
     * or the meaning does not fit the column's target.
     */
    public function meaning(ColumnMapping $column, string $text): ?ValueMeaning
    {
        $meaning = $column->format?->meaningOf($text);

        return $meaning?->fits(TargetField::tryFrom((string) $column->target)) ? $meaning : null;
    }

    /**
     * The items of an option name or option value cell, in order, with empty
     * items kept so that the items of paired columns still pair by position.
     * With item parts, each item keeps only its name part on a name column and
     * its value part on a value column, or else its first part not dropped.
     *
     * @return list<string>
     */
    public function pairItems(ColumnMapping $column, string $text): array
    {
        $parts = $column->format?->parts() ?? [];
        $wanted = $column->is(TargetField::VariantOptionName) ? ItemPart::Name : ItemPart::Value;
        $itemSeparator = $column->itemSeparator();

        return array_map(function (string $item) use ($parts, $wanted, $itemSeparator): string {
            if ($parts === []) {
                return Str::squish($item);
            }

            $pieces = ListItems::pieces($item, $itemSeparator, count($parts));
            $index = array_search($wanted, $parts, true);

            if ($index === false) {
                $index = array_search(true, array_map(fn (ItemPart $part): bool => $part !== ItemPart::Skip, $parts), true);
            }

            return $index === false ? '' : Str::squish($pieces[$index] ?? '');
        }, ListItems::split($text, $column->listSeparator()));
    }

    /**
     * The `Name:Value` items of a cell, split on the column's list separator
     * and then on its item separator. With item parts, each item is split
     * into at most as many parts as described, the last keeping any further
     * separator; without them, it splits on its first item separator. Items
     * without a name or a value are left out.
     *
     * @return list<array{name: string, value: string}>
     */
    public function namedItems(ColumnMapping $column, string $text): array
    {
        $parts = $column->format?->parts() ?? [];
        $itemSeparator = $column->itemSeparator();

        if ($parts === []) {
            return ListItems::pairs($text, $column->listSeparator(), $itemSeparator);
        }

        $nameIndex = array_search(ItemPart::Name, $parts, true);
        $valueIndex = array_search(ItemPart::Value, $parts, true);

        if ($nameIndex === false || $valueIndex === false) {
            return [];
        }

        $items = [];

        foreach (ListItems::split($text, $column->listSeparator()) as $item) {
            $pieces = ListItems::pieces($item, $itemSeparator, count($parts));
            $name = Str::squish($pieces[$nameIndex] ?? '');
            $value = Str::squish($pieces[$valueIndex] ?? '');

            if ($name !== '' && $value !== '') {
                $items[] = ['name' => $name, 'value' => $value];
            }
        }

        return $items;
    }

    /**
     * The image URLs of a cell, each once, without the column's size suffix.
     *
     * @return list<string>
     */
    public function images(ColumnMapping $column, string $text): array
    {
        return array_values(array_unique(array_map(
            fn (string $url): string => $this->withoutImageSuffix($column->format, $url),
            ListItems::distinct($text, $column->listSeparator()),
        )));
    }

    /**
     * A URL without the size suffix where it stands before the file extension
     * or before the next "/": `…/12-small_default.jpg` becomes `…/12.jpg`, and
     * `…/12-small_default/name.jpg` becomes `…/12/name.jpg`.
     */
    public function withoutImageSuffix(?ColumnFormat $format, string $url): string
    {
        $suffix = $format?->imageSuffix;

        if (! ColumnFormat::isValidImageSuffix($suffix)) {
            return $url;
        }

        return Str::replaceMatches('/'.preg_quote((string) $suffix, '/').'(?=\.[A-Za-z0-9]+(?:[?#].*)?$|\/)/u', '', $url);
    }
}
