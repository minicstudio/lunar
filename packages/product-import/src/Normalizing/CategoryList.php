<?php

namespace Lunar\ProductImport\Normalizing;

use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Taxonomy\ValueKeys;

/**
 * Reads a category cell as a list of category paths with its column's
 * separators, and writes such a list back as text. The cell is split on the
 * list separator, except where a backslash comes before it, and each part on
 * the path separator. A list separator `none` reads the cell as one
 * category, and a path separator `none` each category as one level.
 */
class CategoryList
{
    /**
     * The category paths of a cell, each once.
     *
     * @return list<list<string>>
     */
    public function parse(string $text, ColumnMapping $column): array
    {
        $pathSeparator = $column->pathSeparator();
        $paths = [];

        foreach (ListItems::split($text, $column->listSeparator()) as $part) {
            $levels = $pathSeparator === '' ? [$part] : Str::of($part)->explode($pathSeparator)->all();

            $path = array_values(array_filter(
                array_map(fn (string $level): string => Str::squish($level), $levels),
                fn (string $level): bool => $level !== '',
            ));

            if ($path !== []) {
                $paths[ValueKeys::category($path)] ??= $path;
            }
        }

        return array_values($paths);
    }

    /**
     * The category paths as text that `parse()` reads back with the column's
     * separators, with a list separator inside a name escaped by a backslash.
     *
     * @param  list<list<string>>  $paths
     */
    public function format(array $paths, ColumnMapping $column): string
    {
        $pathSeparator = $column->pathSeparator();
        $listSeparator = $column->listSeparator();

        return implode($listSeparator === '' ? ' ' : $listSeparator.' ', array_map(
            fn (array $path): string => implode($pathSeparator === '' ? ' ' : ' '.$pathSeparator.' ', array_map(
                fn (string $level): string => $listSeparator === '' ? $level : Str::replace($listSeparator, '\\'.$listSeparator, $level),
                $path,
            )),
            static::paths($paths),
        ));
    }

    /**
     * The paths of a stored category value: a list of paths, or a single
     * path given as a list of levels.
     *
     * @return list<list<string>>
     */
    public static function paths(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        if (! is_array($value[array_key_first($value)])) {
            return [array_values(array_map('strval', $value))];
        }

        return array_values(array_filter(
            array_map(fn (mixed $path): array => is_array($path) ? array_values(array_map('strval', $path)) : [], $value),
            fn (array $path): bool => $path !== [],
        ));
    }
}
