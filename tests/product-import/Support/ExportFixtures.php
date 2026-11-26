<?php

namespace Lunar\Tests\ProductImport\Support;

/**
 * Shop exports that use the export conventions the import reads without
 * knowing the platform, and the AI answers that describe those conventions
 * per column. The files are built at test time:
 *
 * - a row type export: a row type column, `id:N` parents, renumbered option
 *   pairs, coded status, stock, and backorders, escaped cells, entities in
 *   brand names, and headers in English or Hungarian;
 * - a product list export: ";" delimiters, raw and formatted prices, and
 *   thumbnail URLs with a size suffix;
 * - a products file and a variants file with packed `Name:Type:Position`
 *   and `Value:Position` cells, impacts, features, and discounts;
 * - one row per variant with the product's columns repeated.
 */
class ExportFixtures
{
    /**
     * A number format with a decimal comma and no thousands separator.
     *
     * @var array{decimal_separator: string, thousands_separator: string}
     */
    public const COMMA = ['decimal_separator' => ',', 'thousands_separator' => 'none'];

    /**
     * A number format with a decimal dot and no thousands separator.
     *
     * @var array{decimal_separator: string, thousands_separator: string}
     */
    public const DOT = ['decimal_separator' => '.', 'thousands_separator' => 'none'];

    /**
     * The row type export's headers by column key, per admin language.
     *
     * @var array<string, array<string, string>>
     */
    public const ROW_TYPE_HEADERS = [
        'en' => [
            'id' => 'ID', 'type' => 'Type', 'sku' => 'SKU', 'gtin' => 'GTIN, UPC, EAN, or ISBN', 'name' => 'Name',
            'published' => 'Published', 'featured' => 'Is featured?', 'visibility' => 'Visibility in catalog',
            'short_description' => 'Short description', 'description' => 'Description',
            'sale_starts' => 'Date sale price starts', 'sale_ends' => 'Date sale price ends',
            'tax_status' => 'Tax status', 'tax_class' => 'Tax class', 'in_stock' => 'In stock?', 'stock' => 'Stock',
            'backorders' => 'Backorders allowed?', 'weight' => 'Weight (kg)', 'length' => 'Length (cm)',
            'width' => 'Width (cm)', 'height' => 'Height (cm)', 'purchase_note' => 'Purchase note',
            'sale_price' => 'Sale price', 'regular_price' => 'Regular price', 'categories' => 'Categories',
            'tags' => 'Tags', 'shipping_class' => 'Shipping class', 'images' => 'Images', 'parent' => 'Parent',
            'grouped' => 'Grouped products', 'upsells' => 'Upsells', 'cross_sells' => 'Cross-sells',
            'external_url' => 'External URL', 'button_text' => 'Button text', 'brands' => 'Brands',
            'a1_name' => 'Attribute 1 name', 'a1_values' => 'Attribute 1 value(s)', 'a1_visible' => 'Attribute 1 visible',
            'a1_global' => 'Attribute 1 global', 'a1_default' => 'Attribute 1 default',
            'a2_name' => 'Attribute 2 name', 'a2_values' => 'Attribute 2 value(s)', 'a2_visible' => 'Attribute 2 visible',
            'a2_global' => 'Attribute 2 global', 'a2_default' => 'Attribute 2 default',
        ],
        // The Hungarian headers give "Attribute N default" the header of "Attribute N name".
        'hu' => [
            'id' => 'Azonosító', 'type' => 'Típus', 'sku' => 'Cikkszám', 'gtin' => 'GTIN, UPC, EAN vagy ISBN', 'name' => 'Név',
            'published' => 'Közzétéve', 'featured' => 'Kiemelt?', 'visibility' => 'Katalógusban látható',
            'short_description' => 'Rövid leírás', 'description' => 'Leírás',
            'sale_starts' => 'Akciós ár kezdete', 'sale_ends' => 'Akciós ár vége',
            'tax_status' => 'Adó állapot', 'tax_class' => 'Adóosztály', 'in_stock' => 'Készleten?', 'stock' => 'Készlet',
            'backorders' => 'Előrendelhető?', 'weight' => 'Tömeg (kg)', 'length' => 'Hosszúság (cm)',
            'width' => 'Szélesség (cm)', 'height' => 'Magasság (cm)', 'purchase_note' => 'Vásárlási megjegyzés',
            'sale_price' => 'Akciós ár', 'regular_price' => 'Normál ár', 'categories' => 'Kategóriák',
            'tags' => 'Címkék', 'shipping_class' => 'Szállítási osztály', 'images' => 'Képek', 'parent' => 'Szülő',
            'grouped' => 'Csoportosított termékek', 'upsells' => 'Upsell termékek', 'cross_sells' => 'Keresztértékesítés',
            'external_url' => 'Külső URL', 'button_text' => 'Gomb szövege', 'brands' => 'Márkák',
            'a1_name' => 'Tulajdonság neve 1', 'a1_values' => 'Tulajdonság értéke(i) 1', 'a1_visible' => 'Tulajdonság látható 1',
            'a1_global' => 'Globális tulajdonság 1', 'a1_default' => 'Tulajdonság neve 1',
            'a2_name' => 'Tulajdonság neve 2', 'a2_values' => 'Tulajdonság értéke(i) 2', 'a2_visible' => 'Tulajdonság látható 2',
            'a2_global' => 'Globális tulajdonság 2', 'a2_default' => 'Tulajdonság neve 2',
        ],
    ];

    /**
     * The row type export's column order per language. The Hungarian export
     * lists brands before categories; the attribute columns come last in both.
     *
     * @var array<string, list<string>>
     */
    public const ROW_TYPE_ORDER = [
        'en' => [
            'id', 'type', 'sku', 'gtin', 'name', 'published', 'featured', 'visibility', 'short_description', 'description',
            'sale_starts', 'sale_ends', 'tax_status', 'tax_class', 'in_stock', 'stock', 'backorders', 'weight', 'length',
            'width', 'height', 'purchase_note', 'sale_price', 'regular_price', 'categories', 'tags', 'shipping_class',
            'images', 'parent', 'grouped', 'upsells', 'cross_sells', 'external_url', 'button_text', 'brands',
            'a1_name', 'a1_values', 'a1_visible', 'a1_global', 'a1_default', 'a2_name', 'a2_values', 'a2_visible', 'a2_global', 'a2_default',
        ],
        'hu' => [
            'id', 'type', 'sku', 'gtin', 'name', 'published', 'featured', 'visibility', 'short_description', 'description',
            'sale_starts', 'sale_ends', 'tax_status', 'tax_class', 'in_stock', 'stock', 'backorders', 'weight', 'length',
            'width', 'height', 'purchase_note', 'sale_price', 'regular_price', 'brands', 'categories', 'tags', 'shipping_class',
            'images', 'parent', 'grouped', 'upsells', 'cross_sells', 'external_url', 'button_text',
            'a1_name', 'a1_values', 'a1_visible', 'a1_global', 'a1_default', 'a2_name', 'a2_values', 'a2_visible', 'a2_global', 'a2_default',
        ],
    ];

    /**
     * What the AI answers for each column of the row type export, by column
     * key: the target and the column's formats and conventions. Other
     * columns are ignored.
     *
     * @var array<string, array<int|string, mixed>>
     */
    public const ROW_TYPE_TARGETS = [
        'id' => ['source.id'],
        'type' => ['source.type', 'list' => ',', 'values' => [
            'simple' => 'single', 'variable' => 'parent', 'variation' => 'variant', 'grouped' => 'skip', 'external' => 'skip',
            'virtual' => 'not_shippable', 'downloadable' => 'not_shippable',
        ]],
        'sku' => ['variant.sku'],
        'gtin' => ['variant.gtin'],
        'name' => ['product.name', 'locale' => 'ro'],
        'published' => ['product.status', 'values' => ['1' => 'published', '0' => 'draft', '-1' => 'draft', '2' => 'draft']],
        'description' => ['product.description', 'locale' => 'ro'],
        'sale_starts' => ['variant.sale_starts', 'date' => 'Y-m-d G:i:s'],
        'sale_ends' => ['variant.sale_ends', 'date' => 'Y-m-d G:i:s'],
        'in_stock' => ['variant.stock_status', 'values' => ['1' => 'in_stock', '0' => 'out_of_stock', 'backorder' => 'on_backorder']],
        'stock' => ['variant.stock', 'number' => self::DOT],
        'backorders' => ['variant.backorders', 'values' => ['1' => 'yes', '0' => 'no', 'notify' => 'yes']],
        'weight' => ['variant.weight', 'number' => self::DOT],
        'length' => ['variant.length', 'number' => self::DOT],
        'width' => ['variant.width', 'number' => self::DOT],
        'height' => ['variant.height', 'number' => self::DOT],
        'sale_price' => ['variant.sale_price', 'number' => self::COMMA],
        'regular_price' => ['variant.price', 'number' => self::COMMA],
        'categories' => ['product.category', 'list' => ',', 'path' => '>'],
        'tags' => ['product.tags', 'list' => ','],
        'images' => ['product.images', 'list' => ','],
        'parent' => ['source.parent'],
        'upsells' => ['product.upsells', 'list' => ','],
        'cross_sells' => ['product.cross_sells', 'list' => ','],
        'brands' => ['product.brand'],
        'a1_name' => ['variant.option_name', 'pair' => 1, 'list' => ','],
        'a1_values' => ['variant.option_value', 'pair' => 1, 'list' => ','],
        'a2_name' => ['variant.option_name', 'pair' => 2, 'list' => ','],
        'a2_values' => ['variant.option_value', 'pair' => 2, 'list' => ','],
    ];

    /**
     * The product list export's headers.
     *
     * @var list<string>
     */
    public const LIST_HEADERS = ['Product ID', 'Image', 'Name', 'Reference', 'Category', 'Price (tax excl.)', 'Price (tax incl.)', 'Quantity', 'Status'];

    /**
     * The products file's headers.
     *
     * @var list<string>
     */
    public const PRODUCT_HEADERS = [
        'Product ID', 'Active (0/1)', 'Name *', 'Categories (x,y,z...)', 'Price tax excluded', 'Tax rules ID', 'Wholesale price',
        'On sale (0/1)', 'Discount amount', 'Discount percent', 'Discount from (yyyy-mm-dd)', 'Discount to (yyyy-mm-dd)',
        'Reference #', 'Supplier reference #', 'Supplier', 'Manufacturer', 'EAN13', 'UPC', 'MPN', 'Ecotax', 'Width', 'Height',
        'Depth', 'Weight', 'Delivery time of in-stock products', 'Delivery time of out-of-stock products with allowed orders',
        'Quantity', 'Minimal quantity', 'Low stock level', 'Send me an email when the quantity is under this level', 'Visibility',
        'Additional shipping cost', 'Unity', 'Unit price', 'Summary', 'Description', 'Tags (x,y,z...)', 'Meta title',
        'Meta keywords', 'Meta description', 'URL rewritten', 'Text when in stock', 'Text when backorder allowed',
        'Available for order (0 = No, 1 = Yes)', 'Product available date', 'Product creation date', 'Show price (0 = No, 1 = Yes)',
        'Image URLs (x,y,z...)', 'Image alt texts (x,y,z...)', 'Delete existing images (0 = No, 1 = Yes)',
        'Feature(Name:Value:Position)', 'Available online only (0 = No, 1 = Yes)', 'Condition', 'Customizable (0 = No, 1 = Yes)',
        'Uploadable files (0 = No, 1 = Yes)', 'Text fields (0 = No, 1 = Yes)', 'Out of stock action', 'Virtual product',
        'File URL', 'Number of allowed downloads', 'Expiration date', 'Number of days', 'ID / Name of shop',
        'Advanced stock management', 'Depends On Stock', 'Warehouse', 'Accessories (x,y,z...)',
    ];

    /**
     * The variants file's headers.
     *
     * @var list<string>
     */
    public const VARIANT_HEADERS = [
        'Product ID*', 'Attribute (Name:Type:Position)*', 'Value (Value:Position)*', 'Supplier reference', 'Reference', 'EAN13',
        'UPC', 'MPN', 'Wholesale price', 'Impact on price', 'Ecotax', 'Quantity', 'Minimal quantity', 'Low stock level',
        'Impact on weight', 'Default (0 = No, 1 = Yes)', 'Combination available date', 'Image position', 'Image URLs (x,y,z...)',
        'Image alt texts (x,y,z...)', 'ID / Name of shop', 'Advanced Stock Managment', 'Depends on stock', 'Warehouse',
    ];

    /**
     * The headers of the export with one row per variant.
     *
     * @var list<string>
     */
    public const VARIANT_ROW_HEADERS = ['Product ID', 'SKU', 'Name', 'Base price', 'Price impact', 'Options', 'Stock', 'Weight', 'Active'];

    /**
     * The row type export as a CSV file with a BOM, in the given admin
     * language. Attribute columns grow during the export, so the rows before
     * the first product with attributes are shorter than the header.
     *
     * Rows: 2 a virtual single product without attributes; 3 the parent
     * "Rochie vară" (ID 57) with the plain attribute "Material" and the
     * option "Mărime"; 4 its variant S (ID 58); 5 the single product "Geacă
     * puf" (ID 60) with the attribute "Culoare" and a sale; 6–8 the parent
     * "Tricou ofertă" (ID 80) and its variants, one of them "'-20%"; 9 a
     * grouped row; 10 an external row; 11–89 single products; 90 the
     * variant M (ID 59) of "Rochie vară".
     */
    public static function rowTypeExport(string $language = 'en'): string
    {
        $order = self::ROW_TYPE_ORDER[$language];
        $rows = [array_map(fn (string $key): string => self::ROW_TYPE_HEADERS[$language][$key], $order)];
        $attributesSeen = false;

        foreach (self::rowTypeProducts() as $product) {
            $attributesSeen = $attributesSeen || isset($product['a1_name']);
            $cells = [];

            foreach ($order as $key) {
                if (! $attributesSeen && $key === 'a1_name') {
                    break;
                }

                $cells[] = $product[$key] ?? '';
            }

            $rows[] = $cells;
        }

        return SpreadsheetFixtures::csv($rows, ',', null, true);
    }

    /**
     * The AI answer for the row type export: each column by its key, and of
     * two equal Hungarian headers the first as the option name and the
     * second ignored.
     *
     * @return array<string, mixed>
     */
    public static function rowTypeAnswer(string $language = 'en'): array
    {
        $headers = [];
        $specs = [];
        $seen = [];

        foreach (self::ROW_TYPE_ORDER[$language] as $index => $key) {
            $header = self::ROW_TYPE_HEADERS[$language][$key];
            $headers[$index] = $header;

            if (! isset($seen[$header]) && isset(self::ROW_TYPE_TARGETS[$key])) {
                $specs[$index] = self::ROW_TYPE_TARGETS[$key];
            }

            $seen[$header] = true;
        }

        return self::answer($headers, $specs, ['strategy' => 'by_parent'], ['weight_unit' => 'kg', 'length_unit' => 'cm']);
    }

    /**
     * The rows of the row type export, in export order, by column key.
     *
     * @return list<array<string, string>>
     */
    public static function rowTypeProducts(): array
    {
        $single = ['featured' => '0', 'visibility' => 'visible', 'tax_status' => 'taxable', 'published' => '1', 'backorders' => '0'];

        $products = [
            [...$single, 'id' => '10', 'type' => 'simple, virtual', 'sku' => 'CARD-10', 'name' => 'Card cadou', 'in_stock' => '1', 'stock' => '', 'regular_price' => '100', 'categories' => 'Cadouri', 'purchase_note' => 'Mulțumim!'],
            [
                ...$single, 'id' => '57', 'type' => 'variable', 'sku' => 'RV-57', 'name' => 'Rochie vară', 'published' => '2',
                'description' => 'Rochie lejeră.\nBumbac 100%.', 'in_stock' => '1', 'stock' => '10', 'weight' => '0.500',
                'categories' => 'Femei > Rochii, Femei > Noutăți', 'tags' => 'vara, bumbac',
                'images' => 'https://shop.example.com/uploads/rv-1.jpg, https://shop.example.com/uploads/rv-2.jpg',
                'upsells' => 'id:60', 'brands' => 'Dolce &amp;amp; Gabbana', 'shipping_class' => 'standard',
                'a1_name' => 'Material', 'a1_values' => 'Bumbac', 'a1_visible' => '1', 'a1_global' => '0', 'a1_default' => '',
                'a2_name' => 'Mărime', 'a2_values' => 'S, M', 'a2_visible' => '1', 'a2_global' => '1', 'a2_default' => 'S',
            ],
            [
                ...$single, 'id' => '58', 'type' => 'variation', 'sku' => 'RV-57-S', 'gtin' => '5941234567890', 'name' => 'Rochie vară - S',
                'in_stock' => 'backorder', 'stock' => '', 'backorders' => 'notify', 'weight' => '1.250', 'regular_price' => '1256,59',
                'parent' => 'id:57', 'tax_class' => 'parent', 'a1_name' => 'Mărime', 'a1_values' => 'S', 'a1_visible' => '', 'a1_global' => '1',
            ],
            [
                ...$single, 'id' => '60', 'type' => 'simple', 'sku' => 'GP-60', 'name' => 'Geacă puf', 'in_stock' => '1', 'stock' => '7',
                'weight' => '1.250', 'length' => '60', 'width' => '45', 'height' => '5', 'sale_price' => '999,00', 'regular_price' => '1256,59',
                'sale_starts' => '2026-11-01 0:00:00', 'sale_ends' => '2026-12-24 23:59:59', 'categories' => 'Femei > Geci', 'tags' => 'iarna',
                'images' => 'https://shop.example.com/uploads/gp-1.jpg', 'cross_sells' => 'RV-57-S', 'brands' => 'Furnizor Textil',
                'a1_name' => 'Culoare', 'a1_values' => 'Negru', 'a1_visible' => '1', 'a1_global' => '1',
            ],
            [...$single, 'id' => '80', 'type' => 'variable', 'name' => 'Tricou ofertă', 'in_stock' => '1', 'categories' => 'Femei > Tricouri', 'a1_name' => 'Ofertă', 'a1_values' => 'Standard, -20%', 'a1_visible' => '1', 'a1_global' => '0'],
            [...$single, 'id' => '81', 'type' => 'variation', 'sku' => 'TO-80-STD', 'name' => 'Tricou ofertă - Standard', 'in_stock' => '1', 'stock' => '5', 'regular_price' => '49,90', 'parent' => 'id:80', 'a1_name' => 'Ofertă', 'a1_values' => 'Standard'],
            [...$single, 'id' => '82', 'type' => 'variation', 'sku' => 'TO-80-20', 'name' => 'Tricou ofertă - -20%', 'in_stock' => '1', 'stock' => '3', 'regular_price' => '39,90', 'parent' => 'id:80', 'a1_name' => 'Ofertă', 'a1_values' => "'-20%"],
            [...$single, 'id' => '90', 'type' => 'grouped', 'sku' => 'SET-90', 'name' => 'Set vară', 'grouped' => 'id:10, id:60'],
            [...$single, 'id' => '95', 'type' => 'external', 'sku' => 'EXT-95', 'name' => 'Carte partener', 'regular_price' => '45', 'external_url' => 'https://partner.example.com/carte', 'button_text' => 'Cumpără'],
        ];

        for ($i = 1; $i <= 79; $i++) {
            $products[] = [...$single, 'id' => (string) (100 + $i), 'type' => 'simple', 'sku' => "P-{$i}", 'name' => "Produs {$i}", 'in_stock' => '1', 'stock' => '2', 'regular_price' => '19,90', 'categories' => 'Diverse', 'a1_name' => '', 'a1_values' => ''];
        }

        $products[] = [
            ...$single, 'id' => '59', 'type' => 'variation', 'sku' => 'RV-57-M', 'name' => 'Rochie vară - M', 'in_stock' => '1', 'stock' => '4',
            'regular_price' => '1199,00', 'parent' => 'id:57', 'a1_name' => 'Mărime', 'a1_values' => 'M', 'a1_global' => '1',
        ];

        return $products;
    }

    /**
     * The product list export with ";" delimiters: a raw price without tax
     * ("1256.590000"), a price with tax formatted for a Romanian ("1.503,85
     * lei") or Hungarian ("1 503,85 lei" with no-break spaces) employee,
     * thumbnail URLs with a size suffix, and an escaped negative stock.
     */
    public static function productList(string $locale = 'ro'): string
    {
        $space = "\u{00A0}";
        $price = $locale === 'hu' ? "1{$space}503,85{$space}lei" : '1.503,85 lei';

        return SpreadsheetFixtures::csv([
            self::LIST_HEADERS,
            ['12', 'https://shop.example.com/img/p/1/2/12-small_default.jpg', 'Cămașă', 'CAM-12', 'Rochii', '1256.590000', $price, '10', '1'],
            ['14', 'https://shop.example.com/img/p/1/4/14-small_default.jpg', 'Curea', 'CUR-14', 'Accesorii', '100.000000', $locale === 'hu' ? "119,00{$space}lei" : '119,00 lei', "'-3", '0'],
        ], ';');
    }

    /**
     * The AI answer for the product list: the price column the store's tax
     * setting needs, the other ignored, the image size suffix, and the
     * "Status" column, which marks stock availability, ignored.
     *
     * @return array<string, mixed>
     */
    public static function productListAnswer(string $locale = 'ro'): array
    {
        $inclusive = (bool) config('lunar.pricing.stored_inclusive_of_tax', false);

        return self::answer(self::LIST_HEADERS, [
            0 => ['source.id'],
            1 => ['product.images', 'suffix' => '-small_default', 'list' => 'none'],
            2 => ['product.name', 'locale' => 'ro'],
            3 => ['variant.sku'],
            4 => ['product.category', 'list' => 'none', 'path' => 'none'],
            5 => $inclusive ? null : ['variant.price', 'number' => self::DOT],
            6 => $inclusive ? ['variant.price', 'number' => ['decimal_separator' => ',', 'thousands_separator' => $locale === 'hu' ? 'space' : '.']] : null,
            7 => ['variant.stock', 'number' => self::DOT],
        ], ['strategy' => 'per_row'], ['weight_unit' => null, 'length_unit' => null]);
    }

    /**
     * The products file: shirt 12 with variants in the variants file, belt
     * 14 without, and the virtual gift card 16.
     */
    public static function products(): string
    {
        $rows = [
            ['Product ID' => '12', 'Active (0/1)' => '1', 'Name *' => 'Cămașă', 'Categories (x,y,z...)' => 'Femei/Rochii, Noutăți',
                'Price tax excluded' => '100', 'Tax rules ID' => '1', 'Wholesale price' => '40', 'Reference #' => 'CAM-12',
                'Manufacturer' => 'Furnizor Textil', 'Weight' => '0.3', 'Quantity' => '5', 'Minimal quantity' => '1',
                'Meta title' => 'Cămașă albă', 'Feature(Name:Value:Position)' => 'Compoziție:Bumbac 100%:1',
                'Image URLs (x,y,z...)' => 'https://shop.example.com/img/p/1/2/12.jpg', 'Out of stock action' => '2',
                'Virtual product' => '0', 'Accessories (x,y,z...)' => '14'],
            ['Product ID' => '14', 'Active (0/1)' => '1', 'Name *' => 'Curea', 'Categories (x,y,z...)' => 'Accesorii',
                'Price tax excluded' => '200.00', 'Discount percent' => '25', 'Reference #' => 'CUR-14', 'EAN13' => '5941234567890',
                'Weight' => '0.2', 'Quantity' => '4', 'Out of stock action' => '1', 'Virtual product' => '0', 'Accessories (x,y,z...)' => '12'],
            ['Product ID' => '16', 'Active (0/1)' => '0', 'Name *' => 'Card cadou', 'Categories (x,y,z...)' => 'Cadouri',
                'Price tax excluded' => '50', 'Reference #' => 'CARD-16', 'Quantity' => '100', 'Out of stock action' => '0',
                'Virtual product' => '1'],
        ];

        return SpreadsheetFixtures::csv(self::lines(self::PRODUCT_HEADERS, $rows), ';');
    }

    /**
     * The variants file: S / Alb and M / Alb of shirt 12, and one variant of
     * product 99, which neither file has.
     */
    public static function variants(): string
    {
        $rows = [
            ['Product ID*' => '12', 'Attribute (Name:Type:Position)*' => 'Size:select:0, Color:color:1', 'Value (Value:Position)*' => 'S:0, Alb:1',
                'Reference' => 'CAM-12-S-ALB', 'Impact on price' => '0', 'Quantity' => '3', 'Impact on weight' => '0', 'Default (0 = No, 1 = Yes)' => '1'],
            ['Product ID*' => '12', 'Attribute (Name:Type:Position)*' => 'Size:select:0, Color:color:1', 'Value (Value:Position)*' => 'M:0, Alb:1',
                'Reference' => 'CAM-12-M-ALB', 'Impact on price' => '10.00', 'Quantity' => '2', 'Impact on weight' => '0', 'Default (0 = No, 1 = Yes)' => '0'],
            ['Product ID*' => '99', 'Attribute (Name:Type:Position)*' => 'Size:select:0', 'Value (Value:Position)*' => 'L:0',
                'Reference' => 'X-99-L', 'Impact on price' => '0', 'Quantity' => '1', 'Impact on weight' => '0', 'Default (0 = No, 1 = Yes)' => '1'],
        ];

        return SpreadsheetFixtures::csv(self::lines(self::VARIANT_HEADERS, $rows), ';');
    }

    /**
     * The AI answer for the products file, with the codes of its active,
     * out-of-stock action, and virtual product columns and the parts of its
     * features.
     *
     * @return array<string, mixed>
     */
    public static function productsAnswer(): array
    {
        return self::answerByHeader(self::PRODUCT_HEADERS, [
            'Product ID' => ['source.id'],
            'Active (0/1)' => ['product.status', 'values' => ['1' => 'published', '0' => 'draft']],
            'Name *' => ['product.name', 'locale' => 'ro'],
            'Categories (x,y,z...)' => ['product.category', 'list' => ',', 'path' => '/'],
            'Price tax excluded' => ['variant.price', 'number' => self::DOT],
            'Discount amount' => ['variant.discount_amount', 'number' => self::DOT],
            'Discount percent' => ['variant.discount_percent', 'number' => self::DOT],
            'Discount from (yyyy-mm-dd)' => ['variant.sale_starts', 'date' => 'Y-m-d'],
            'Discount to (yyyy-mm-dd)' => ['variant.sale_ends', 'date' => 'Y-m-d'],
            'Reference #' => ['variant.sku'],
            'Manufacturer' => ['product.brand'],
            'EAN13' => ['variant.ean'],
            'UPC' => ['variant.gtin'],
            'MPN' => ['variant.mpn'],
            'Width' => ['variant.width', 'number' => self::DOT],
            'Height' => ['variant.height', 'number' => self::DOT],
            'Depth' => ['variant.length', 'number' => self::DOT],
            'Weight' => ['variant.weight', 'number' => self::DOT],
            'Quantity' => ['variant.stock', 'number' => self::DOT],
            'Minimal quantity' => ['variant.min_quantity', 'number' => self::DOT],
            'Description' => ['product.description', 'locale' => 'ro'],
            'Tags (x,y,z...)' => ['product.tags', 'list' => ','],
            'Image URLs (x,y,z...)' => ['product.images', 'list' => ','],
            'Feature(Name:Value:Position)' => ['product.properties', 'parts' => ['name', 'value', 'skip'], 'list' => ',', 'item' => ':'],
            'Out of stock action' => ['variant.backorders', 'values' => ['0' => 'no', '1' => 'yes', '2' => 'empty']],
            'Virtual product' => ['variant.shippable', 'values' => ['1' => 'no', '0' => 'yes']],
            'Accessories (x,y,z...)' => ['product.cross_sells', 'list' => ','],
        ], ['strategy' => 'per_row'], ['weight_unit' => 'kg', 'length_unit' => 'cm']);
    }

    /**
     * The AI answer for the variants file, with the parts of its packed
     * attribute names and values.
     *
     * @return array<string, mixed>
     */
    public static function variantsAnswer(): array
    {
        return self::answerByHeader(self::VARIANT_HEADERS, [
            'Product ID*' => ['source.parent'],
            'Attribute (Name:Type:Position)*' => ['variant.option_name', 'pair' => 1, 'parts' => ['name', 'skip', 'skip'], 'list' => ',', 'item' => ':'],
            'Value (Value:Position)*' => ['variant.option_value', 'pair' => 1, 'parts' => ['value', 'skip'], 'list' => ',', 'item' => ':'],
            'Reference' => ['variant.sku'],
            'EAN13' => ['variant.ean'],
            'UPC' => ['variant.gtin'],
            'MPN' => ['variant.mpn'],
            'Impact on price' => ['variant.price_impact', 'number' => self::DOT],
            'Quantity' => ['variant.stock', 'number' => self::DOT],
            'Minimal quantity' => ['variant.min_quantity', 'number' => self::DOT],
            'Impact on weight' => ['variant.weight_impact', 'number' => self::DOT],
            'Image URLs (x,y,z...)' => ['variant.image', 'list' => ','],
        ], ['strategy' => 'by_parent'], ['weight_unit' => 'kg', 'length_unit' => 'cm']);
    }

    /**
     * An export with one row per variant: product 7 in S, M, and L, with the
     * product's columns repeated on every row.
     */
    public static function variantRows(): string
    {
        return SpreadsheetFixtures::csv([
            self::VARIANT_ROW_HEADERS,
            ['7', 'TR-7-S', 'Tricou', '50.000000', '0.000000', 'Size:S', '3', '0.200000', '1'],
            ['7', 'TR-7-M', 'Tricou', '50.000000', '0.000000', 'Size:M', '4', '0.200000', '1'],
            ['7', 'TR-7-L', 'Tricou', '50.000000', '5.000000', 'Size:L', '2', '0.200000', '1'],
        ], ';');
    }

    /**
     * The AI answer for the export with one row per variant: grouped by the
     * product ID, with packed `Name:Value` options.
     *
     * @return array<string, mixed>
     */
    public static function variantRowsAnswer(): array
    {
        return self::answer(self::VARIANT_ROW_HEADERS, [
            0 => ['group_key'],
            1 => ['variant.sku'],
            2 => ['product.name', 'locale' => 'ro'],
            3 => ['variant.price', 'number' => self::DOT],
            4 => ['variant.price_impact', 'number' => self::DOT],
            5 => ['variant.options', 'list' => ',', 'item' => ':'],
            6 => ['variant.stock', 'number' => self::DOT],
            7 => ['variant.weight', 'number' => self::DOT],
            8 => ['product.status', 'values' => ['1' => 'published', '0' => 'draft']],
        ], ['strategy' => 'by_column', 'column' => 0], ['weight_unit' => 'kg', 'length_unit' => null]);
    }

    /**
     * An AI answer from column specs by header.
     *
     * @param  list<string>  $headers
     * @param  array<string, array<int|string, mixed>>  $specs
     * @param  array{strategy: string, column?: ?int}  $grouping
     * @param  array{weight_unit: ?string, length_unit: ?string}  $units
     * @return array<string, mixed>
     */
    public static function answerByHeader(array $headers, array $specs, array $grouping, array $units): array
    {
        $byIndex = [];

        foreach ($headers as $index => $header) {
            if (isset($specs[$header])) {
                $byIndex[$index] = $specs[$header];
            }
        }

        return self::answer($headers, $byIndex, $grouping, $units);
    }

    /**
     * An AI answer: every column with its target (`ignore` without a spec),
     * language, pair number, number or date format, separators, code
     * meanings, item parts, and image size suffix, and the sheet's units.
     *
     * @param  list<string>  $headers
     * @param  array<int, ?array<int|string, mixed>>  $specs  By column index: `[target, 'locale' => …, 'pair' => …, 'number' => …, 'date' => …, 'list' => …, 'path' => …, 'item' => …, 'values' => [code => meaning], 'parts' => […], 'suffix' => …]`.
     * @param  array{strategy: string, column?: ?int}  $grouping
     * @param  array{weight_unit: ?string, length_unit: ?string}  $units
     * @return array<string, mixed>
     */
    public static function answer(array $headers, array $specs, array $grouping, array $units): array
    {
        $columns = [];

        foreach (array_keys($headers) as $index) {
            $spec = $specs[$index] ?? null;
            $values = $spec['values'] ?? null;

            $columns[] = [
                'index' => $index,
                'target' => $spec[0] ?? 'ignore',
                'confidence' => 0.95,
                'locale' => $spec['locale'] ?? null,
                'option_name' => null,
                'pair' => $spec['pair'] ?? null,
                'number_format' => $spec['number'] ?? null,
                'date_format' => $spec['date'] ?? null,
                'values' => $values === null ? null : array_map(
                    fn (int|string $code, string $meaning): array => ['value' => (string) $code, 'meaning' => $meaning],
                    array_keys($values),
                    array_values($values),
                ),
                'item_parts' => $spec['parts'] ?? null,
                'image_suffix' => $spec['suffix'] ?? null,
                'list_separator' => $spec['list'] ?? null,
                'path_separator' => $spec['path'] ?? null,
                'item_separator' => $spec['item'] ?? null,
            ];
        }

        return [
            'columns' => $columns,
            'grouping' => ['strategy' => $grouping['strategy'], 'column' => $grouping['column'] ?? null, 'confidence' => 0.9],
            'units' => $units,
        ];
    }

    /**
     * Rows in the headers' order, every row as long as the header.
     *
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     * @return list<list<string>>
     */
    protected static function lines(array $headers, array $rows): array
    {
        return [$headers, ...array_map(fn (array $row): array => array_map(fn (string $header): string => $row[$header] ?? '', $headers), $rows)];
    }
}
