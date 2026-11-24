<?php

namespace Lunar\ProductImport\Data;

use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Support\KeyNormalizer;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * What one column of a sheet holds. A null target is unmapped. A column of
 * numbers or dates carries its format, and an option name or option value
 * column its pair number.
 */
#[MapName(SnakeCaseMapper::class)]
class ColumnMapping extends Data
{
    public function __construct(
        public int $index,
        public string $header,
        public ?string $target = null,
        public ?string $locale = null,
        public ?int $optionId = null,
        public ?string $optionName = null,
        public ?float $confidence = null,
        public ?string $suggestedTarget = null,
        public bool $flagged = false,
        public ?ColumnFormat $format = null,
        public ?int $pair = null,
    ) {}

    /**
     * Whether the column feeds a field.
     */
    public function isMapped(): bool
    {
        return $this->target !== null && $this->target !== TargetField::Ignore->value;
    }

    /**
     * Whether the column maps to the given fixed target.
     */
    public function is(TargetField $field): bool
    {
        return $this->target === $field->value;
    }

    /**
     * The separators the column's target needs: `list` for a list target,
     * `path` for a category column, and `item` for a column of packed items
     * (`variant.options`, `product.properties`, or an option pair column with
     * item parts).
     *
     * @return list<string>
     */
    public function neededSeparators(): array
    {
        $field = $this->isMapped() ? TargetField::tryFrom((string) $this->target) : null;

        return $field?->separators(($this->format?->itemParts ?? []) !== []) ?? [];
    }

    /**
     * What the column's list cells split on: an empty string when each cell
     * holds one item.
     */
    public function listSeparator(): string
    {
        return ColumnFormat::splitOn($this->format?->listSeparator);
    }

    /**
     * What the column's category paths split into levels on: an empty string
     * when categories have one level.
     */
    public function pathSeparator(): string
    {
        return ColumnFormat::splitOn($this->format?->pathSeparator);
    }

    /**
     * What the column's packed items split into parts on: an empty string
     * when its items are not packed.
     */
    public function itemSeparator(): string
    {
        return ColumnFormat::splitOn($this->format?->itemSeparator);
    }

    /**
     * The key of the option this column fills: the existing option's id, or
     * the normalized name of a new option. Null for other targets.
     */
    public function optionKey(): ?string
    {
        if (! $this->is(TargetField::VariantOption)) {
            return null;
        }

        if ($this->optionId !== null) {
            return 'option:'.$this->optionId;
        }

        $name = KeyNormalizer::key($this->optionName);

        return $name === '' ? null : 'new:'.$name;
    }

    /**
     * The field path of the column's normalized value, for example
     * `variant.price`, `product.name.ro`, `variant.option.option:12`, or
     * `variant.option_name.1`.
     */
    public function fieldPath(): ?string
    {
        if ($this->is(TargetField::GroupKey)) {
            return TargetField::GroupKey->value;
        }

        if (! $this->isMapped()) {
            return null;
        }

        if ($this->is(TargetField::VariantOption)) {
            return ($key = $this->optionKey()) === null ? null : 'variant.option.'.$key;
        }

        if ($this->is(TargetField::VariantOptionName) || $this->is(TargetField::VariantOptionValue)) {
            return $this->pair === null || $this->pair < 1 ? null : $this->target.'.'.$this->pair;
        }

        return $this->locale !== null && $this->locale !== ''
            ? $this->target.'.'.$this->locale
            : $this->target;
    }
}
