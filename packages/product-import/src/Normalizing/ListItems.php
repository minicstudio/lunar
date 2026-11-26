<?php

namespace Lunar\ProductImport\Normalizing;

use Illuminate\Support\Str;

/**
 * Splits a list cell on its column's list separator, except where a
 * backslash comes before it. An empty separator reads the cell as one item.
 */
class ListItems
{
    /**
     * The trimmed items of a cell, in order. Empty items are kept, so items of
     * paired columns still pair by position.
     *
     * @return list<string>
     */
    public static function split(string $text, string $separator): array
    {
        $separator = Str::trim($separator);

        if ($separator === '') {
            return [Str::trim($text)];
        }

        return Str::of($text)
            ->split('/(?<!\\\\)'.preg_quote($separator, '/').'/u')
            ->map(fn (string $item): string => Str::trim(Str::replace('\\'.$separator, $separator, $item)))
            ->values()
            ->all();
    }

    /**
     * The filled items of a cell, each once regardless of case.
     *
     * @return list<string>
     */
    public static function distinct(string $text, string $separator): array
    {
        $items = [];

        foreach (static::split($text, $separator) as $item) {
            $item = Str::squish($item);

            if ($item !== '') {
                $items[Str::lower($item)] ??= $item;
            }
        }

        return array_values($items);
    }

    /**
     * The `Name:Value` items of a cell, each split at its first item
     * separator. Items without a name or a value, and every item when the
     * column has no item separator, are left out.
     *
     * @return list<array{name: string, value: string}>
     */
    public static function pairs(string $text, string $separator, string $itemSeparator): array
    {
        if ($itemSeparator === '') {
            return [];
        }

        $pairs = [];

        foreach (static::split($text, $separator) as $item) {
            if (! Str::contains($item, $itemSeparator)) {
                continue;
            }

            $name = Str::squish(Str::before($item, $itemSeparator));
            $value = Str::squish(Str::after($item, $itemSeparator));

            if ($name !== '' && $value !== '') {
                $pairs[] = ['name' => $name, 'value' => $value];
            }
        }

        return $pairs;
    }

    /**
     * An item split on the item separator into at most the given number of
     * pieces, the last keeping any further separator. Without an item
     * separator, the item is one piece.
     *
     * @return list<string>
     */
    public static function pieces(string $item, string $itemSeparator, int $limit): array
    {
        if ($itemSeparator === '') {
            return [$item];
        }

        return Str::of($item)->explode($itemSeparator, max(1, $limit))->values()->all();
    }
}
