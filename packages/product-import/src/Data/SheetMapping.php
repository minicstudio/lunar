<?php

namespace Lunar\ProductImport\Data;

use Lunar\ProductImport\Enums\TargetField;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The mapping of one sheet. The AI proposal, staff edits, and saved profiles
 * all use this format.
 */
#[MapName(SnakeCaseMapper::class)]
class SheetMapping extends Data
{
    /**
     * @param  list<ColumnMapping>  $columns
     * @param  ?string  $weightUnit  The unit of the sheet's weights, a key of `lunar.shipping.measurements.weight`.
     * @param  ?string  $lengthUnit  The unit of the sheet's dimensions, a key of `lunar.shipping.measurements.length`.
     */
    public function __construct(
        public int $headerRow = 1,
        public ?int $productTypeId = null,
        public Grouping $grouping = new Grouping,
        #[DataCollectionOf(ColumnMapping::class)]
        public array $columns = [],
        public ?string $weightUnit = null,
        public ?string $lengthUnit = null,
    ) {}

    /**
     * The mapping of a column, by its index.
     */
    public function column(int $index): ?ColumnMapping
    {
        foreach ($this->columns as $column) {
            if ($column->index === $index) {
                return $column;
            }
        }

        return null;
    }

    /**
     * The column whose normalized value has the given field path.
     */
    public function columnForField(string $field): ?ColumnMapping
    {
        foreach ($this->columns as $column) {
            if ($column->fieldPath() === $field) {
                return $column;
            }
        }

        return null;
    }

    /**
     * The columns that feed a field.
     *
     * @return list<ColumnMapping>
     */
    public function mappedColumns(): array
    {
        return array_values(array_filter($this->columns, fn (ColumnMapping $column): bool => $column->isMapped()));
    }

    /**
     * The columns mapped to a fixed target.
     *
     * @return list<ColumnMapping>
     */
    public function columnsFor(TargetField $field): array
    {
        return array_values(array_filter($this->columns, fn (ColumnMapping $column): bool => $column->is($field)));
    }

    /**
     * Whether any column maps to the fixed target.
     */
    public function maps(TargetField $field): bool
    {
        return $this->columnsFor($field) !== [];
    }

    /**
     * Whether any column maps to a dimension (length, width, or height).
     */
    public function mapsDimensions(): bool
    {
        foreach ($this->mappedColumns() as $column) {
            if (TargetField::tryFrom((string) $column->target)?->isDimension()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The option name and option value columns, by pair number.
     *
     * @return array<int, array{name: list<ColumnMapping>, value: list<ColumnMapping>}>
     */
    public function pairs(): array
    {
        $pairs = [];

        foreach ([TargetField::VariantOptionName, TargetField::VariantOptionValue] as $field) {
            foreach ($this->columnsFor($field) as $column) {
                if ($column->pair === null) {
                    continue;
                }

                $pairs[$column->pair] ??= ['name' => [], 'value' => []];
                $pairs[$column->pair][$field === TargetField::VariantOptionName ? 'name' : 'value'][] = $column;
            }
        }

        ksort($pairs);

        return $pairs;
    }

    /**
     * The column holding the group key, if any.
     */
    public function groupKeyColumn(): ?ColumnMapping
    {
        return $this->columnsFor(TargetField::GroupKey)[0] ?? null;
    }

    /**
     * The option keys this sheet fills, column index => key.
     *
     * @return array<int, string>
     */
    public function optionKeys(): array
    {
        $keys = [];

        foreach ($this->columnsFor(TargetField::VariantOption) as $column) {
            if (($key = $column->optionKey()) !== null) {
                $keys[$column->index] = $key;
            }
        }

        return $keys;
    }
}
