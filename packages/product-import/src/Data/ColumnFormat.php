<?php

namespace Lunar\ProductImport\Data;

use Illuminate\Support\Str;
use Lunar\ProductImport\Enums\ItemPart;
use Lunar\ProductImport\Enums\ValueMeaning;
use Lunar\ProductImport\Support\KeyNormalizer;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * How one column writes its values: the decimal and thousands separators of
 * its numbers or the PHP date format of its dates, the separators of its
 * lists, category paths, and packed items, and the conventions of an export,
 * which also belong to the column: the meaning of each code, the parts of
 * its packed list items, and the size suffix of its image URLs. A format
 * belongs to one column, never to a sheet: an export may write prices with
 * the shop's decimal comma but weights with a dot, and two sheets may
 * separate their categories differently.
 */
#[MapName(SnakeCaseMapper::class)]
class ColumnFormat extends Data
{
    /**
     * The separator of a column whose cells hold one item, or whose
     * categories have one level.
     */
    public const NONE = 'none';

    /**
     * A list, path, or item separator: one to three characters without
     * letters or digits.
     */
    public const SEPARATOR_PATTERN = '/^[^\p{L}\p{N}]{1,3}$/u';

    /**
     * The characters and length an image size suffix may have.
     */
    public const IMAGE_SUFFIX_PATTERN = '/^[A-Za-z0-9_-]{1,50}$/';

    /**
     * The decimal separators, by name.
     *
     * @var array<string, string>
     */
    public const DECIMAL_SEPARATORS = ['dot' => '.', 'comma' => ','];

    /**
     * The thousands separators, by name. `none` is an empty string.
     *
     * @var array<string, string>
     */
    public const THOUSANDS_SEPARATORS = ['none' => '', 'dot' => '.', 'comma' => ',', 'space' => ' ', 'apostrophe' => "'"];

    /**
     * The characters a date format may hold: PHP date format characters,
     * separators, and backslash escapes.
     */
    public const DATE_FORMAT_PATTERN = '/^(?:[dDjlNSwzWFmMntLoXxYyaABgGhHisuveIOPpTZcrU .,\/:\-]|\\\\.)+$/';

    /**
     * @param  ?string  $decimalSeparator  "." or ",".
     * @param  ?string  $thousandsSeparator  "" (none), ".", ",", " ", or "'".
     * @param  ?string  $dateFormat  A PHP date format, such as `Y-m-d G:i:s`.
     * @param  array<string, string>  $values  The meaning (`ValueMeaning`) of each code, keyed by the code's `KeyNormalizer` key.
     * @param  list<string>  $itemParts  The parts (`ItemPart`) of each packed list item, split on the item separator.
     * @param  ?string  $imageSuffix  The size suffix the column's image URLs carry, such as `-small_default`.
     * @param  ?string  $listSeparator  The characters between the items of a list cell, or `none`.
     * @param  ?string  $pathSeparator  The characters between the levels of a category path, or `none`.
     * @param  ?string  $itemSeparator  The characters between the parts of a packed item, such as ":".
     */
    public function __construct(
        public ?string $decimalSeparator = null,
        public ?string $thousandsSeparator = null,
        public ?string $dateFormat = null,
        public array $values = [],
        public array $itemParts = [],
        public ?string $imageSuffix = null,
        public ?string $listSeparator = null,
        public ?string $pathSeparator = null,
        public ?string $itemSeparator = null,
    ) {}

    /**
     * A separator as it is stored: `none`, or one to three characters without
     * letters or digits, trimmed. Null for anything else.
     */
    public static function separator(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = Str::trim($value);

        if (Str::lower($value) === self::NONE) {
            return self::NONE;
        }

        return $value !== '' && Str::isMatch(self::SEPARATOR_PATTERN, $value) ? $value : null;
    }

    /**
     * The stored separator of a kind: `list`, `path`, or `item`.
     */
    public function separatorOf(string $kind): ?string
    {
        return match ($kind) {
            'list' => $this->listSeparator,
            'path' => $this->pathSeparator,
            'item' => $this->itemSeparator,
            default => null,
        };
    }

    /**
     * Whether a stored separator is usable.
     */
    public static function isValidSeparator(?string $separator): bool
    {
        return $separator !== null && self::separator($separator) === $separator;
    }

    /**
     * The characters a stored separator splits on: an empty string for `none`
     * and for a missing or unusable separator, which never splits.
     */
    public static function splitOn(?string $separator): string
    {
        return self::isValidSeparator($separator) && $separator !== self::NONE ? (string) $separator : '';
    }

    /**
     * Code meanings, keyed by each code's `KeyNormalizer` key. Codes without
     * a key and unknown meanings are left out.
     *
     * @param  array<int|string, ValueMeaning|string>  $meanings  Code => meaning.
     */
    public static function meanings(array $meanings): self
    {
        return (new self)->withMeanings($meanings);
    }

    /**
     * The same format with the given code meanings instead of its own.
     *
     * @param  array<int|string, ValueMeaning|string>  $meanings  Code => meaning.
     */
    public function withMeanings(array $meanings): self
    {
        $values = [];

        foreach ($meanings as $code => $meaning) {
            $key = KeyNormalizer::key((string) $code);
            $meaning = $meaning instanceof ValueMeaning ? $meaning : ValueMeaning::tryFrom((string) $meaning);

            if ($key !== '' && $meaning !== null) {
                $values[$key] = $meaning->value;
            }
        }

        $format = clone $this;
        $format->values = $values;

        return $format;
    }

    /**
     * The meaning of a cell's code, or null when the column gives it none.
     */
    public function meaningOf(string $text): ?ValueMeaning
    {
        $key = KeyNormalizer::key($text);

        if ($key === '' || ! array_key_exists($key, $this->values)) {
            return null;
        }

        return ValueMeaning::tryFrom((string) $this->values[$key]);
    }

    /**
     * The parts of each packed list item, or an empty list when the column
     * describes none.
     *
     * @return list<ItemPart>
     */
    public function parts(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $part): ?ItemPart => ItemPart::tryFrom((string) $part),
            $this->itemParts,
        )));
    }

    /**
     * Whether item parts name at most one name and one value, and every part
     * is known.
     *
     * @param  list<string>  $parts
     */
    public static function isValidItemParts(array $parts): bool
    {
        if ($parts === []) {
            return false;
        }

        $counts = array_count_values(array_map('strval', $parts));

        foreach (array_keys($counts) as $part) {
            if (ItemPart::tryFrom((string) $part) === null) {
                return false;
            }
        }

        return ($counts[ItemPart::Name->value] ?? 0) <= 1 && ($counts[ItemPart::Value->value] ?? 0) <= 1;
    }

    /**
     * Whether an image size suffix holds only letters, digits, "-", and "_",
     * and at most 50 characters.
     */
    public static function isValidImageSuffix(?string $suffix): bool
    {
        return $suffix !== null && Str::isMatch(self::IMAGE_SUFFIX_PATTERN, $suffix);
    }

    /**
     * Whether the format describes nothing: no separators, no date format,
     * and no conventions.
     */
    public function isEmpty(): bool
    {
        return $this->decimalSeparator === null
            && $this->thousandsSeparator === null
            && $this->dateFormat === null
            && $this->values === []
            && $this->itemParts === []
            && $this->imageSuffix === null
            && $this->listSeparator === null
            && $this->pathSeparator === null
            && $this->itemSeparator === null;
    }

    /**
     * A number format.
     */
    public static function number(string $decimalSeparator, string $thousandsSeparator = ''): self
    {
        return new self($decimalSeparator, $thousandsSeparator);
    }

    /**
     * A date format.
     */
    public static function date(string $dateFormat): self
    {
        return new self(dateFormat: $dateFormat);
    }

    /**
     * The fixed form of cells the spreadsheet stores as numbers: a decimal
     * dot and no thousands separator.
     */
    public static function fixedNumber(): self
    {
        return new self('.', '');
    }

    /**
     * Whether the column has a usable number format: a known decimal and a
     * known thousands separator that differ.
     */
    public function hasNumberFormat(): bool
    {
        return in_array($this->decimalSeparator, self::DECIMAL_SEPARATORS, true)
            && in_array($this->thousandsSeparator, self::THOUSANDS_SEPARATORS, true)
            && $this->decimalSeparator !== $this->thousandsSeparator;
    }

    /**
     * Whether the column has a usable date format.
     */
    public function hasDateFormat(): bool
    {
        return self::isValidDateFormat($this->dateFormat);
    }

    /**
     * Whether a date format holds only date format characters and separators.
     */
    public static function isValidDateFormat(?string $format): bool
    {
        return $format !== null && Str::trim($format) !== '' && Str::isMatch(self::DATE_FORMAT_PATTERN, $format);
    }

    /**
     * The name of the decimal separator (`dot`, `comma`), or null.
     */
    public function decimalName(): ?string
    {
        $name = array_search($this->decimalSeparator, self::DECIMAL_SEPARATORS, true);

        return $name === false ? null : $name;
    }

    /**
     * The name of the thousands separator (`none`, `dot`, `comma`, `space`,
     * `apostrophe`), or null.
     */
    public function thousandsName(): ?string
    {
        $name = array_search($this->thousandsSeparator, self::THOUSANDS_SEPARATORS, true);

        return $name === false ? null : $name;
    }

    /**
     * The number format in words, for messages: "a decimal dot and no
     * thousands separator".
     */
    public function describeNumber(): string
    {
        if (! $this->hasNumberFormat()) {
            return __('lunarpanel.product-import::mapping.formats.missing');
        }

        return __('lunarpanel.product-import::mapping.formats.number', [
            'decimal' => __('lunarpanel.product-import::mapping.formats.decimal.'.$this->decimalName()),
            'thousands' => __('lunarpanel.product-import::mapping.formats.thousands.'.$this->thousandsName()),
        ]);
    }
}
