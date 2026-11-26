<?php

namespace Lunar\ProductImport\Reading;

use DateTimeInterface;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Turns a spreadsheet cell into the string stored as its raw value, without
 * losing data: whole numbers keep all digits, decimals keep their precision,
 * dates become ISO 8601, formulas give their result, and text is unchanged.
 */
class CellValueFormatter
{
    /**
     * Excel keeps 15 significant digits.
     */
    protected const SIGNIFICANT_DIGITS = 15;

    /**
     * Format one cell. Empty cells give null.
     */
    public function format(Cell $cell): ?string
    {
        return $this->read($cell)[0];
    }

    /**
     * Format one cell, and say whether the spreadsheet stores it as a number
     * or a date. Such a cell's text has a fixed form: a decimal dot, or an
     * ISO 8601 date.
     *
     * @return array{0: ?string, 1: bool}
     */
    public function read(Cell $cell): array
    {
        $value = $cell->getValue();

        if ($cell->getDataType() === DataType::TYPE_FORMULA || (is_string($value) && Str::startsWith($value, '='))) {
            $value = $this->calculatedValue($cell);
        }

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        $isNumber = is_int($value) || is_float($value);
        $isDate = $isNumber && $this->hasDateFormat($cell, $value);
        $text = $this->formatValue($value, $isDate);

        return [$text, $text !== null && ($isNumber || $value instanceof DateTimeInterface)];
    }

    /**
     * Format a plain value. Numbers formatted as dates become ISO 8601 dates.
     */
    public function formatValue(mixed $value, bool $isDate = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->formatDate($value);
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($isDate && (is_int($value) || is_float($value))) {
            try {
                return $this->formatDate(Date::excelToDateTimeObject($value));
            } catch (Throwable) {
                // Not a valid serial date: keep the number.
            }
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->formatFloat($value);
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }

    /**
     * Format a float without exponent and without binary noise.
     */
    public function formatFloat(float $value): string
    {
        if (! is_finite($value)) {
            return (string) $value;
        }

        if ($value == floor($value) && abs($value) < 1e18) {
            return sprintf('%.0f', $value);
        }

        $magnitude = (int) floor(log10(abs($value)));
        $decimals = max(0, min(20, self::SIGNIFICANT_DIGITS - $magnitude - 1));

        $formatted = number_format($value, $decimals, '.', '');

        if (Str::contains($formatted, '.')) {
            $formatted = Str::rtrim(Str::rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * Format a date as `Y-m-d`, with the time only when it is not midnight.
     */
    protected function formatDate(DateTimeInterface $date): string
    {
        return $date->format('H:i:s') === '00:00:00'
            ? $date->format('Y-m-d')
            : $date->format('Y-m-d\TH:i:s');
    }

    /**
     * The result of a formula: the value the file cached, or a fresh calculation.
     */
    protected function calculatedValue(Cell $cell): mixed
    {
        $cached = $cell->getOldCalculatedValue();

        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        try {
            return $cell->getCalculatedValue();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether the cell's number format shows a date.
     */
    protected function hasDateFormat(Cell $cell, int|float $value): bool
    {
        try {
            return Date::isDateTime($cell, $value);
        } catch (Throwable) {
            return false;
        }
    }
}
