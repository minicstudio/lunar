<?php

namespace Lunar\ProductImport\Data;

use Illuminate\Support\Str;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Taxonomy\ValueKeys;

/**
 * A row's values keyed by field path (for example `variant.price` in minor
 * units or `product.name.ro`). A mapped cell that is empty holds null. Cells
 * that could not be read keep their raw text under `invalid`. Values that
 * structuring derived from the row's product row (inherited fields, prices
 * with an impact, options and properties from cells) are kept apart under
 * `derived`, with structure notes under `notes`, so structuring again starts
 * from the cells.
 */
final class NormalizedRow
{
    /**
     * The key under which unreadable raw values are stored.
     */
    public const INVALID_KEY = '_invalid';

    /**
     * The key under which derived values are stored.
     */
    public const DERIVED_KEY = '_derived';

    /**
     * The key under which structure notes are stored.
     */
    public const NOTES_KEY = '_notes';

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $invalid
     * @param  array<string, mixed>  $derived
     * @param  array<string, mixed>  $notes
     */
    public function __construct(
        public array $values = [],
        public array $invalid = [],
        public array $derived = [],
        public array $notes = [],
    ) {}

    /**
     * The stored form.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            ...$this->values,
            self::INVALID_KEY => $this->invalid,
            self::DERIVED_KEY => $this->derived,
            self::NOTES_KEY => $this->notes,
        ], fn (mixed $value, string $key): bool => ! in_array($key, [self::INVALID_KEY, self::DERIVED_KEY, self::NOTES_KEY], true) || $value !== [], ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Rebuild from the stored form.
     *
     * @param  ?array<string, mixed>  $stored
     */
    public static function fromArray(?array $stored): self
    {
        $stored ??= [];
        $invalid = $stored[self::INVALID_KEY] ?? [];
        $derived = $stored[self::DERIVED_KEY] ?? [];
        $notes = $stored[self::NOTES_KEY] ?? [];
        unset($stored[self::INVALID_KEY], $stored[self::DERIVED_KEY], $stored[self::NOTES_KEY]);

        return new self($stored, is_array($invalid) ? $invalid : [], is_array($derived) ? $derived : [], is_array($notes) ? $notes : []);
    }

    /**
     * The normalized values, then the derived ones, with staff corrections on
     * top. A corrected field is no longer unreadable.
     *
     * @param  ?array<string, mixed>  $normalized
     * @param  ?array<string, mixed>  $overrides
     * @param  bool  $withDerived  False for the values before structuring.
     */
    public static function effective(?array $normalized, ?array $overrides, bool $withDerived = true): self
    {
        $row = self::fromArray($normalized);

        if ($withDerived) {
            foreach ($row->derived as $field => $value) {
                $row->values[$field] = $value;
                unset($row->invalid[$field]);
            }
        }

        foreach ($overrides ?? [] as $field => $value) {
            $row->values[$field] = $value;
            unset($row->invalid[$field]);
        }

        return $row;
    }

    /**
     * A structure note, such as an unresolved parent reference.
     */
    public function note(string $key): mixed
    {
        return $this->notes[$key] ?? null;
    }

    /**
     * A value by field path.
     */
    public function get(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    /**
     * Whether the field was mapped (it may still be empty).
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    /**
     * Whether the field holds a value.
     */
    public function filled(string $field): bool
    {
        $value = $this->values[$field] ?? null;

        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * The options the row names in its cells, by option key (`name:<normalized
     * name>`) => value. When the row names an option twice, the first value
     * counts, for validation as for the write. Items without a name or a
     * value are left out.
     *
     * @return array<string, string>
     */
    public function namedOptions(): array
    {
        $options = [];

        foreach ((array) $this->get(RowStructurer::NAMED_OPTIONS) as $option) {
            $name = is_array($option) ? (string) ($option['name'] ?? '') : '';
            $value = is_array($option) ? (string) ($option['value'] ?? '') : '';

            if ($name !== '' && $value !== '') {
                $options[ValueKeys::namedOption($name)] ??= $value;
            }
        }

        return $options;
    }

    /**
     * The values whose field paths start with the given prefix, keyed by the rest.
     *
     * @return array<string, mixed>
     */
    public function withPrefix(string $prefix): array
    {
        $values = [];

        foreach ($this->values as $field => $value) {
            if (Str::startsWith($field, $prefix)) {
                $values[Str::after($field, $prefix)] = $value;
            }
        }

        return $values;
    }
}
