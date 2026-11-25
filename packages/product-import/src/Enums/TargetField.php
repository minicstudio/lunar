<?php

namespace Lunar\ProductImport\Enums;

use Illuminate\Support\Str;
use Lunar\ProductImport\Support\TargetDefinition;

/**
 * The fixed column targets. Product attributes use the dynamic
 * `attribute.<handle>` target on top of these.
 */
enum TargetField: string
{
    case ProductName = 'product.name';
    case ProductDescription = 'product.description';
    case ProductStatus = 'product.status';
    case ProductSlug = 'product.slug';
    case ProductBrand = 'product.brand';
    case ProductCategory = 'product.category';
    case ProductTags = 'product.tags';
    case ProductProperties = 'product.properties';
    case ProductImages = 'product.images';
    case ProductUpsells = 'product.upsells';
    case ProductCrossSells = 'product.cross_sells';
    case VariantSku = 'variant.sku';
    case VariantEan = 'variant.ean';
    case VariantGtin = 'variant.gtin';
    case VariantMpn = 'variant.mpn';
    case VariantPrice = 'variant.price';
    case VariantPriceImpact = 'variant.price_impact';
    case VariantSale = 'variant.sale_price';
    case VariantSaleStarts = 'variant.sale_starts';
    case VariantSaleEnds = 'variant.sale_ends';
    case VariantDiscountAmount = 'variant.discount_amount';
    case VariantDiscountPercent = 'variant.discount_percent';
    case VariantStock = 'variant.stock';
    case VariantStockStatus = 'variant.stock_status';
    case VariantBackorders = 'variant.backorders';
    case VariantMinQuantity = 'variant.min_quantity';
    case VariantShippable = 'variant.shippable';
    case VariantWeight = 'variant.weight';
    case VariantWeightImpact = 'variant.weight_impact';
    case VariantLength = 'variant.length';
    case VariantWidth = 'variant.width';
    case VariantHeight = 'variant.height';
    case VariantImage = 'variant.image';
    case VariantOption = 'variant.option';
    case VariantOptionName = 'variant.option_name';
    case VariantOptionValue = 'variant.option_value';
    case VariantOptions = 'variant.options';
    case SourceId = 'source.id';
    case SourceParent = 'source.parent';
    case SourceType = 'source.type';
    case GroupKey = 'group_key';
    case Ignore = 'ignore';

    /**
     * The prefix of attribute targets.
     */
    public const ATTRIBUTE_PREFIX = 'attribute.';

    /**
     * Whether the target is a product attribute.
     */
    public static function isAttribute(?string $target): bool
    {
        return $target !== null && Str::startsWith($target, self::ATTRIBUTE_PREFIX);
    }

    /**
     * The attribute handle of an attribute target.
     */
    public static function attributeHandle(string $target): string
    {
        return Str::after($target, self::ATTRIBUTE_PREFIX);
    }

    /**
     * Whether the target takes a store language.
     */
    public function takesLocale(): bool
    {
        return in_array($this, [self::ProductName, self::ProductDescription, self::ProductSlug], true);
    }

    /**
     * Whether the target is one of a pair of columns with a pair number: the
     * option names and the option values.
     */
    public function isPair(): bool
    {
        return in_array($this, [self::VariantOptionName, self::VariantOptionValue], true);
    }

    /**
     * Whether the target's cells hold packed list items whose `:`-separated
     * parts a column may describe (`ItemPart`).
     */
    public function takesItemParts(): bool
    {
        return in_array($this, [self::VariantOptionName, self::VariantOptionValue, self::VariantOptions, self::ProductProperties], true);
    }

    /**
     * Whether the target's cells hold image URLs, which may carry a size
     * suffix.
     */
    public function takesImageSuffix(): bool
    {
        return in_array($this, [self::ProductImages, self::VariantImage], true);
    }

    /**
     * Whether the target holds a number, read with its column's number format.
     */
    public function isNumber(): bool
    {
        return in_array($this, [
            self::VariantPrice,
            self::VariantPriceImpact,
            self::VariantSale,
            self::VariantDiscountAmount,
            self::VariantDiscountPercent,
            self::VariantStock,
            self::VariantMinQuantity,
            self::VariantWeight,
            self::VariantWeightImpact,
            self::VariantLength,
            self::VariantWidth,
            self::VariantHeight,
        ], true);
    }

    /**
     * Whether the target holds a whole number.
     */
    public function isWholeNumber(): bool
    {
        return in_array($this, [self::VariantStock, self::VariantMinQuantity], true);
    }

    /**
     * Whether the target holds an amount of money, stored in the default
     * currency's minor units.
     */
    public function isMoney(): bool
    {
        return in_array($this, [
            self::VariantPrice,
            self::VariantPriceImpact,
            self::VariantSale,
            self::VariantDiscountAmount,
        ], true);
    }

    /**
     * Whether a number of the target may be negative: impacts may, prices,
     * stock, and measures may not.
     */
    public function allowsNegative(): bool
    {
        return in_array($this, [self::VariantPriceImpact, self::VariantWeightImpact], true);
    }

    /**
     * Whether the target is a dimension, stored in the sheet's length unit.
     */
    public function isDimension(): bool
    {
        return in_array($this, [self::VariantLength, self::VariantWidth, self::VariantHeight], true);
    }

    /**
     * Whether the target holds a date, read with its column's date format.
     */
    public function isDate(): bool
    {
        return in_array($this, [self::VariantSaleStarts, self::VariantSaleEnds], true);
    }

    /**
     * Whether the target's cell is a list split on its column's list
     * separator.
     */
    public function isList(): bool
    {
        return in_array($this, [
            self::ProductCategory,
            self::ProductTags,
            self::ProductProperties,
            self::ProductImages,
            self::ProductUpsells,
            self::ProductCrossSells,
            self::VariantImage,
            self::VariantOptionName,
            self::VariantOptionValue,
            self::VariantOptions,
            self::SourceType,
        ], true);
    }

    /**
     * Whether the target's list items are always packed `Name:Value` items,
     * split on their column's item separator.
     */
    public function isPackedItems(): bool
    {
        return in_array($this, [self::VariantOptions, self::ProductProperties], true);
    }

    /**
     * The separators a column mapped to the target needs: `list` for a list
     * target, `path` for categories, and `item` for packed items. An option
     * name or value column needs an item separator only when it describes
     * item parts.
     *
     * @return list<string>
     */
    public function separators(bool $hasItemParts = false): array
    {
        return array_keys(array_filter([
            'list' => $this->isList(),
            'path' => $this === self::ProductCategory,
            'item' => $this->isPackedItems() || ($this->isPair() && $hasItemParts),
        ]));
    }

    /**
     * The kind of format a column mapped to this target needs: `number`,
     * `date`, or null.
     */
    public function format(): ?string
    {
        return match (true) {
            $this->isNumber() => TargetDefinition::FORMAT_NUMBER,
            $this->isDate() => TargetDefinition::FORMAT_DATE,
            default => null,
        };
    }

    /**
     * Whether the target may be mapped to at most one column per sheet. Names
     * and descriptions are used once per language, options once per option,
     * and option names and values once per pair number.
     */
    public function isSingleValue(): bool
    {
        return ! $this->takesLocale() && ! $this->isPair() && ! in_array($this, [self::VariantOption, self::Ignore], true);
    }
}
