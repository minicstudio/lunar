<?php

namespace Lunar\ProductImport\Normalizing;

use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnFormat;

/**
 * Reads a number strictly in its column's format. It removes currency
 * symbols and codes, accepts the thousands separator only between groups of
 * three digits after a first group of one to three, and the decimal
 * separator at most once, followed by digits. Anything else is not a number:
 * the separator is never decided per value.
 */
class NumberParser
{
    /**
     * Currency symbols and codes removed before parsing.
     */
    protected const CURRENCY_PATTERN = '/(lei|ron|eur|euro|huf|ft|usd|gbp|€|\$|£)/iu';

    /**
     * The characters a space thousands separator matches: a space, a no-break
     * space, and a narrow no-break space.
     */
    protected const SPACE_SEPARATOR = '[ \x{00A0}\x{202F}]';

    /**
     * The number as a plain decimal string ("1249.90"), or null when the
     * value does not read in the format.
     */
    public function parse(?string $text, ?ColumnFormat $format): ?string
    {
        if ($text === null || $format === null || ! $format->hasNumberFormat()) {
            return null;
        }

        $value = Str::trim((string) Str::replaceMatches(self::CURRENCY_PATTERN, '', $text));
        $sign = '';

        if (Str::startsWith($value, ['-', '+'])) {
            $sign = Str::startsWith($value, '-') ? '-' : '';
            $value = Str::trim(Str::substr($value, 1));
        }

        $thousands = match ($format->thousandsSeparator) {
            '' => null,
            ' ' => self::SPACE_SEPARATOR,
            default => preg_quote((string) $format->thousandsSeparator, '/'),
        };

        $decimal = (string) $format->decimalSeparator;
        $integer = $thousands === null ? '\d*' : '(?:\d{1,3}(?:'.$thousands.'\d{3})+|\d*)';
        $pattern = '/^'.$integer.'(?:'.preg_quote($decimal, '/').'\d+)?$/u';

        if (! Str::isMatch($pattern, $value)) {
            return null;
        }

        // The integer part holds only digits and thousands separators, so the
        // decimal separator, when present, splits the value once.
        $hasFraction = Str::contains($value, $decimal);
        $digits = (string) Str::replaceMatches('/\D/', '', $hasFraction ? Str::beforeLast($value, $decimal) : $value);
        $fraction = $hasFraction ? Str::afterLast($value, $decimal) : '';

        if ($digits === '' && $fraction === '') {
            return null;
        }

        $digits = Str::ltrim($digits, '0');
        $number = ($digits === '' ? '0' : $digits).($fraction !== '' ? '.'.$fraction : '');

        return $sign === '-' && Str::trim($number, '0.') !== '' ? '-'.$number : $number;
    }
}
