<?php

namespace Lunar\ProductImport\Normalizing;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Lunar\ProductImport\Data\ColumnFormat;
use Throwable;

/**
 * Reads a date strictly in its column's date format. A value with parse
 * errors or warnings (such as a 13th month) is not a date.
 */
class DateParser
{
    /**
     * The stored form of a date.
     */
    public const STORED_FORMAT = 'Y-m-d H:i:s';

    /**
     * The fixed forms of cells the spreadsheet stores as dates.
     *
     * @var list<string>
     */
    public const TYPED_FORMATS = ['Y-m-d', 'Y-m-d\TH:i:s'];

    /**
     * The date as `Y-m-d H:i:s`, or null when it does not read in the format.
     */
    public function parse(?string $text, ?string $format): ?string
    {
        if ($text === null || Str::trim($text) === '' || ! ColumnFormat::isValidDateFormat($format)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, Str::trim($text));
        } catch (Throwable) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format(self::STORED_FORMAT);
    }

    /**
     * A cell the spreadsheet stores as a date, read in its fixed form.
     */
    public function parseTyped(?string $text): ?string
    {
        foreach (self::TYPED_FORMATS as $format) {
            if (($date = $this->parse($text, $format)) !== null) {
                return $date;
            }
        }

        return null;
    }
}
