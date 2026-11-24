<?php

namespace Lunar\ProductImport\Structuring;

use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueMeaning;
use Lunar\ProductImport\Normalizing\ListItems;

/**
 * A row's type read with its column's meanings. A row type cell holds one
 * item or a list split on its column's list separator (`simple, virtual`).
 * The first item with a role decides the
 * row's role: `single` is a product with one variant, `parent` a product
 * whose variants are other rows, `variant` a variant, and `skip` a row that
 * is not imported. `not_shippable` on any item makes the variant not
 * shippable. A row without a role item follows the general rule.
 */
final readonly class RowTypes
{
    public function __construct(
        public RowRole|SkippedRow|null $role = null,
        public bool $notShippable = false,
    ) {}

    /**
     * Read the row type of a row of the sheet. A sheet without a row type
     * column, and a row without a type, give no role.
     */
    public static function read(SheetMapping $mapping, NormalizedRow $values): self
    {
        $column = $mapping->columnsFor(TargetField::SourceType)[0] ?? null;
        $type = $values->get(TargetField::SourceType->value);

        if ($column === null || ! is_string($type) || $type === '') {
            return new self;
        }

        $role = null;
        $notShippable = false;

        foreach (ListItems::split($type, $column->listSeparator()) as $item) {
            $meaning = $item === '' ? null : $column->format?->meaningOf($item);

            if ($meaning === null || ! $meaning->fits(TargetField::SourceType)) {
                continue;
            }

            $notShippable = $notShippable || $meaning === ValueMeaning::NotShippable;

            if ($role === null) {
                $role = $meaning === ValueMeaning::Skip ? new SkippedRow($item) : $meaning->role();
            }
        }

        return new self($role, $notShippable);
    }

    /**
     * Whether the type means a product with one variant, whose option pairs
     * and `Name:Value` items are properties: a single variant has no options
     * to choose between.
     */
    public function isSingle(): bool
    {
        return $this->role === RowRole::ProductAndVariant;
    }
}
