<?php

namespace Lunar\ProductImport\Normalizing;

use Illuminate\Support\Str;
use Lunar\ProductImport\Enums\TargetField;

/**
 * Cleans a cell the same way on every sheet, before anything reads it:
 *
 * - the apostrophe that exporters put before "=", "+", "-", "@", a tab, or a
 *   carriage return at the start of a cell, so that spreadsheets do not run
 *   the cell as a formula, is removed;
 * - in descriptions and attributes, a literal `\n` or `\r\n` becomes a line
 *   break;
 * - in names of brands, categories, tags, options, option values, and
 *   properties, HTML entities are decoded until nothing changes, because
 *   some exporters escape a name twice (`&amp;amp;`).
 *
 * None of these sequences is meaningful data in a product cell.
 */
class CellCleaner
{
    /**
     * The characters after which a leading apostrophe is an escape.
     *
     * @var list<string>
     */
    public const ESCAPED_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * The targets whose cells hold names, with HTML entities decoded.
     *
     * @var list<TargetField>
     */
    public const NAME_TARGETS = [
        TargetField::ProductBrand,
        TargetField::ProductCategory,
        TargetField::ProductTags,
        TargetField::ProductProperties,
        TargetField::VariantOption,
        TargetField::VariantOptionName,
        TargetField::VariantOptionValue,
        TargetField::VariantOptions,
    ];

    /**
     * How often entities are decoded at most.
     */
    protected const DECODE_ROUNDS = 5;

    /**
     * The cleaned text of a cell mapped to the target.
     */
    public function clean(?string $target, string $text): string
    {
        $text = $this->withoutEscape($text);
        $field = TargetField::tryFrom((string) $target);

        if ($field === TargetField::ProductDescription || TargetField::isAttribute($target)) {
            return Str::replace(['\\r\\n', '\\n'], "\n", $text);
        }

        if (in_array($field, self::NAME_TARGETS, true)) {
            return $this->decodeEntities($text);
        }

        return $text;
    }

    /**
     * The cell without the apostrophe an exporter put before a character
     * that starts a formula. Any other leading apostrophe stays.
     */
    public function withoutEscape(string $text): string
    {
        if (Str::startsWith($text, "'") && Str::startsWith(Str::substr($text, 1, 1), self::ESCAPED_STARTS)) {
            return Str::substr($text, 1);
        }

        return $text;
    }

    /**
     * The text with HTML entities decoded until nothing changes.
     */
    public function decodeEntities(string $text): string
    {
        for ($round = 0; $round < self::DECODE_ROUNDS; $round++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
        }

        return $text;
    }
}
