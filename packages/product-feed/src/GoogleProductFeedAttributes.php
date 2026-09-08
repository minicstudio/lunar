<?php

namespace Lunar\ProductFeed;

/**
 * Google Merchant Center product feed attributes used by CSV/XML encoders.
 */
final class GoogleProductFeedAttributes
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'price',
        'sale_price',
        'sale_price_effective_date',
        'link',
        'image_link',
        'brand',
        'gtin',
        'mpn',
        'item_group_id',
        'size',
        'color',
        'material',
        'pattern',
        'product_type',
    ];

    /**
     * Optional Google attributes omitted when empty.
     *
     * @var list<string>
     */
    public const OPTIONAL_KEYS = [
        'sale_price',
        'sale_price_effective_date',
        'image_link',
        'brand',
        'gtin',
        'mpn',
        'size',
        'color',
        'material',
        'pattern',
        'product_type',
    ];
}
