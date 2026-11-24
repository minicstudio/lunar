<?php

namespace Lunar\ProductImport\Reading;

use Illuminate\Support\Str;

/**
 * Finds the header row of a sheet among its first non-empty rows.
 */
class HeaderDetector
{
    /**
     * How many non-empty rows are considered.
     */
    public const CANDIDATE_ROWS = 10;

    /**
     * The share of filled cells that must be text, not numbers or dates.
     */
    protected const TEXT_SHARE = 0.5;

    /**
     * The share of the following rows' typical filled cell count that the
     * header must reach. A header with one empty cell above a filled column
     * still counts.
     */
    protected const FILLED_SHARE = 0.8;

    /**
     * Pick the first row whose filled cells are mostly text and that has at
     * least as many filled cells as the rows after it. Without such a row the
     * first non-empty row is the header.
     *
     * @param  array<int, array<int, ?string>>  $rows  The first non-empty rows, keyed by file row number.
     */
    public function detect(array $rows): int
    {
        ksort($rows);

        $candidates = array_slice($rows, 0, self::CANDIDATE_ROWS, true);

        if ($candidates === []) {
            return 1;
        }

        $rowNumbers = array_keys($candidates);
        $filledCounts = array_map(fn (array $cells): int => count($this->filled($cells)), array_values($candidates));

        foreach ($rowNumbers as $position => $rowNumber) {
            $filled = $this->filled($candidates[$rowNumber]);

            if ($filled === [] || ! $this->isMostlyText($filled)) {
                continue;
            }

            $after = array_slice($filledCounts, $position + 1);

            if ($after === [] || count($filled) >= $this->typical($after) * self::FILLED_SHARE) {
                return $rowNumber;
            }
        }

        return $rowNumbers[0];
    }

    /**
     * The non-empty cells of a row.
     *
     * @param  array<int, ?string>  $cells
     * @return list<string>
     */
    protected function filled(array $cells): array
    {
        return array_values(array_filter(
            array_map(fn (?string $cell): string => Str::trim((string) $cell), $cells),
            fn (string $cell): bool => $cell !== '',
        ));
    }

    /**
     * Whether most filled cells are text rather than numbers or dates.
     *
     * @param  list<string>  $cells
     */
    protected function isMostlyText(array $cells): bool
    {
        $text = count(array_filter($cells, fn (string $cell): bool => ! $this->looksNumeric($cell)));

        return $text / count($cells) > self::TEXT_SHARE;
    }

    /**
     * Whether a cell holds a number, a price, or a date.
     */
    protected function looksNumeric(string $cell): bool
    {
        return Str::isMatch('/^[-+]?[\d\s.,]+(\s*(lei|ron|eur|huf|ft|€|\$))?$/iu', $cell)
            || Str::isMatch('/^\d{4}-\d{2}-\d{2}/', $cell);
    }

    /**
     * The median of the following rows' filled cell counts.
     *
     * @param  list<int>  $counts
     */
    protected function typical(array $counts): float
    {
        sort($counts);
        $middle = intdiv(count($counts), 2);

        return count($counts) % 2 === 1
            ? (float) $counts[$middle]
            : ($counts[$middle - 1] + $counts[$middle]) / 2;
    }
}
