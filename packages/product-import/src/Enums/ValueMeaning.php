<?php

namespace Lunar\ProductImport\Enums;

/**
 * What a code in a column means, so a column can write its values in its own
 * codes: a status column's `2` may mean draft, a backorders column's
 * `notify` may mean yes, and a row type column's `variation` may mean a
 * variant row. Each meaning fits only the targets listed in `targets()`.
 */
enum ValueMeaning: string
{
    case Published = 'published';
    case Draft = 'draft';
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case OnBackorder = 'on_backorder';
    case Yes = 'yes';
    case No = 'no';
    case Empty = 'empty';
    case Single = 'single';
    case Parent = 'parent';
    case Variant = 'variant';
    case Skip = 'skip';
    case NotShippable = 'not_shippable';

    /**
     * The meanings a column mapped to the target may give its codes. Empty
     * for a target whose codes have no meaning.
     *
     * @return list<self>
     */
    public static function for(?TargetField $target): array
    {
        return match ($target) {
            TargetField::ProductStatus => [self::Published, self::Draft, self::Empty],
            TargetField::VariantStockStatus => [self::InStock, self::OutOfStock, self::OnBackorder, self::Empty],
            TargetField::VariantBackorders, TargetField::VariantShippable => [self::Yes, self::No, self::Empty],
            TargetField::SourceType => [self::Single, self::Parent, self::Variant, self::Skip, self::NotShippable],
            default => [],
        };
    }

    /**
     * Whether a column mapped to the target may give a code this meaning.
     */
    public function fits(?TargetField $target): bool
    {
        return in_array($this, self::for($target), true);
    }

    /**
     * The role of a row whose row type item has this meaning, or null for a
     * meaning that gives no role.
     */
    public function role(): ?RowRole
    {
        return match ($this) {
            self::Single => RowRole::ProductAndVariant,
            self::Parent => RowRole::Product,
            self::Variant => RowRole::Variant,
            default => null,
        };
    }
}
