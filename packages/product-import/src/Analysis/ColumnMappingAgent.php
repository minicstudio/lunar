<?php

namespace Lunar\ProductImport\Analysis;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ItemPart;
use Lunar\ProductImport\Enums\ValueMeaning;
use Stringable;

/**
 * Suggests which target each column of a sheet holds, each column's number
 * or date format, separators, and export conventions (code meanings, item
 * parts, and the image size suffix), the sheet's weight and length units,
 * and how the rows group into products. It has no tools: it only sees the
 * column descriptions it is prompted with and answers in a fixed JSON
 * structure. Its instructions describe the conventions of shop exports in
 * general terms and name no platform: the AI recognizes an export from its
 * headers and samples.
 */
class ColumnMappingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * The on-demand provider name used when imports have their own key or URL.
     */
    public const PROVIDER_NAME = 'product-import';

    /**
     * The thousands separators the answer may name. `ProposalValidator` reads
     * "none" as no separator and "space" as a space.
     *
     * @var list<string>
     */
    public const THOUSANDS_SEPARATORS = ['none', '.', ',', 'space', "'"];

    /**
     * @param  list<string>  $targets  The allowed target codes.
     * @param  list<string>  $languages  The store's language codes.
     * @param  list<string>  $weightUnits  The store's weight unit codes.
     * @param  list<string>  $lengthUnits  The store's length unit codes.
     */
    public function __construct(
        public array $targets,
        public array $languages,
        public array $weightUnits = [],
        public array $lengthUnits = [],
    ) {}

    /**
     * The instructions the agent follows: the general mapping rules, the
     * formats, separators, and units every answer gives, and the export
     * conventions.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You map the columns of a product spreadsheet to the fields of a webshop catalog. The sheet is a
            supplier's own product list or the product export of another shop. You receive one sheet as JSON:
            its name, its data row count, and for each column its index, header, fill rate, distinct share,
            and sample values. You also receive the allowed targets with their labels and the store's language
            codes. Headers may be in any language and in any order, and some rows may hold fewer cells than
            the header: decide what a column holds from its header and its samples.

            For every column, answer with exactly one allowed target, or "ignore" when the column holds
            nothing the catalog needs, and a confidence between 0 and 1. Use a target that takes a language
            only with one of the given language codes, chosen from the header and the samples. For
            "variant.option" (one column per option, its cells holding the values) give the option's name (for
            example "Size" or "Color") in option_name. When one column holds option names and another their
            values, map them to "variant.option_name" and "variant.option_value" with the same pair number (1,
            2, ...), one name and one value column per pair number. Map a column of "Name:Value" items to
            "variant.options" when they are options, or to "product.properties" when they are plain product
            properties or features. Map each single-value target (SKU, EAN, price, stock, weight, status,
            brand, category, group key, and the other product and variant fields) to at most one column, and
            the product name at most once per language. Use "product.status" for a column that says whether a
            product is published or active. Use "source.id" for the row's own ID in the exporting shop,
            "source.parent" for a reference to the row's product row, and "source.type" for a column that says
            what kind of row each row is. Use "variant.price_impact" and "variant.weight_impact" for amounts
            that are added to the product's price or weight.

            Then say how rows form products: "by_column" when a column holds a code shared by the variants of
            one product, such as a parent code or a product ID repeated on every variant row (map that column
            to "group_key" and give its index), "by_parent" when variant rows reference their product row
            through a "source.parent" column, "by_name" when rows with the same product name are variants of
            one product, or "per_row" when every row is its own product. Give a confidence for the grouping.

            For every column that holds numbers, give its number_format: the decimal separator ("." or ",")
            and the thousands separator ("none", ".", ",", "space", or "'"), read from the samples. The two
            separators always differ. For every column that holds dates, give its date_format as a PHP date
            format, for example "Y-m-d G:i:s" for "2026-12-01 0:00:00" or "d.m.Y" for "24.12.2026". Give null
            for columns without numbers or dates. A format belongs to one column: columns of the same sheet
            may use different separators. An export from a shop with a decimal comma, for example, may write
            its prices with the shop's separator ("1256,59": decimal ",", thousands none) but its weights and
            dimensions always with a dot ("1.250" is 1.25: decimal ".", thousands none). A raw price may have
            six decimals ("1256.590000": decimal ".", thousands none), and a price formatted for display may
            carry a currency and a thousands separator ("1.503,85 lei": decimal ",", thousands "."; "1 503,85
            lei": decimal ",", thousands "space"). When a sample such as "1.250" could be read either way,
            decide from the column's other samples and its meaning. Ignore currency symbols and codes.

            Separators. Every column mapped to a list target (categories, tags, images, up-sells, cross-sells,
            row types, option names, option values, options, and properties) must have its list_separator:
            the characters between the items of one cell, read from the samples, such as "," in "vara, bumbac"
            or "|" in "https://cdn.example/1.jpg|https://cdn.example/2.jpg". Give "none" when each cell holds
            one item. A category column must also have its path_separator: the characters between the levels
            of one category, such as ">" in "Women > Dresses, Women > New" (whose list separator is ",") or
            "/" in "Women/Dresses". Give "none" when categories have no levels. A column of packed items
            ("variant.options", "product.properties", or an option name or value column whose items hold
            several parts) must also have its item_separator: the characters between the parts of one item,
            usually ":" as in "Color:Red, Size:M". A separator is one to three characters without letters or
            digits. Separators belong to one column: the columns and sheets of one import may separate their
            lists differently. Give null for a separator that the column's target does not use.

            Units. When the sheet has a weight column, give units.weight_unit, and when it has a length, width,
            or height column, give units.length_unit, each one of the store's units listed in the answer
            structure. The unit is often named in the header, such as "Weight (g)" or "Length (cm)"; otherwise
            choose the unit the values make likely. Give null only for a unit whose columns the sheet does not
            have.

            Exports of shops follow conventions that you describe per column:

            Row types. When a column says what kind of row each row is, map it to "source.type" and give in
            values the meaning of each type: "single" for a product with one variant, "parent" for a product
            whose variants are on other rows, "variant" for a variant row, "skip" for a kind of row that the
            catalog cannot import (such as grouped products, bundles, or links to products in another shop),
            and "not_shippable" for a type that marks virtual or downloadable products. A cell may hold several
            types in a list, such as "simple, virtual": give a meaning to each type.

            Parent references. Variant rows name their product row by its ID, sometimes written as "id:57", or
            by its SKU. The product row may be on another sheet: a products file and a separate variants file
            are two sheets of one import, and the variants file references the products by product ID or
            reference. Map such a column to "source.parent" and group by_parent.

            Renumbered pairs. Option name and value columns come in numbered pairs, such as "Option 1 name" and
            "Option 1 values". A variant row may number its pairs differently from its product row, because
            pairs are matched by option name: give each column the pair number of its header. Columns that only
            say how an attribute is displayed (visible, global, used for variations, default value) are
            ignored. When two columns share a header, tell them apart by their position and samples: the
            column whose samples are option names is the name, and a following column with the same header
            whose samples are single values is a default and is ignored.

            Packed items. When the items of an option, option name, option value, or properties column pack
            several parts separated by the item separator, give item_parts: one of "name", "value", or "skip"
            for each part, in order, with at most one "name" and one "value". "Size:select:0, Color:color:1"
            (name, type, position) gives ["name", "skip", "skip"]; "S:0, Alb:1" (value, position) gives
            ["value", "skip"]; "Composition:Cotton 100%:1" (name, value, position) gives ["name", "value",
            "skip"]. Give null when each item is a plain "Name:Value" pair or a single name or value.

            Code meanings. For columns mapped to "product.status", "variant.stock_status",
            "variant.backorders", "variant.shippable", or "source.type", give in values the meaning of each
            code: "published" or "draft" for a status; "in_stock", "out_of_stock", or "on_backorder" for a stock
            status; "yes" or "no" for backorders and shippable; and "empty" for a code that leaves the field
            unset, such as a code for the shop's default setting. Give a meaning for each code that the samples
            show, and for the codes that such a column is known to hold even when the samples do not show
            them, such as a status code for products pending review (draft) or a backorders code for allowing
            backorders with a notice (yes). A column whose flag says the opposite of the target, such as a
            virtual product flag mapped to "variant.shippable", gives "1" the meaning "no" and "0" the meaning
            "yes". A column headed like a status that marks stock availability is a stock status. Give an
            empty list for other columns.

            Image size suffixes. When the image URLs of a column point at a resized copy whose file or folder
            name carries a size suffix, such as "-small" in ".../images/12-small.jpg" or ".../12-small/name.jpg",
            give that suffix as image_suffix (letters, digits, "-", and "_") so that the original image is
            downloaded. Give null otherwise.

            Prices with and without tax. The label of the price target says whether the store keeps prices
            with or without tax. When a sheet has both a price with tax and a price without tax, map the one
            that matches the price target to "variant.price" and ignore the other.

            SEO fields and URL slugs. A meta title, meta description, or meta keywords column maps to the
            attribute of the product type that holds it, in the column's language. The labels of attribute
            targets start with their attribute group, such as "Search Engine Optimization › Title", which
            tells such an attribute apart from the product name or description. When the product type has no
            attribute for such a column, ignore the column. A column of URL slugs (the last part of a
            product's web address, such as "rochie-vara") maps to "product.slug" in its language. A column of
            full product URLs is ignored.

            Columns without a catalog counterpart are ignored: tax status and tax classes, shipping classes,
            visibility, featured and sold-individually flags, reviews, purchase notes, downloads and their
            limits, positions, low stock thresholds, external URLs and button texts, customs data, cost,
            wholesale, and unit prices, ecotax, supplier and supplier reference, delivery times, available and
            creation dates, condition, customization and uploadable files, image alt texts, shops, warehouses,
            advanced stock settings, default-variant flags, and custom field columns unless an attribute of
            the product type fits them.
            TEXT;
    }

    /**
     * The provider: the configured one, or an on-demand copy of it with the
     * import's own key or URL, so the AI Assistant keeps its own settings.
     */
    public function provider(): ?string
    {
        $availability = app(AiAvailability::class);
        $provider = $availability->provider();

        if ($provider === null || ! $availability->usesOwnConfiguration()) {
            return $provider;
        }

        Ai::build([
            ...(array) config("ai.providers.{$provider}", []),
            'name' => self::PROVIDER_NAME,
            'driver' => $availability->driver(),
            'key' => $availability->ownKey() ?? config("ai.providers.{$provider}.key"),
            'url' => $availability->ownUrl() ?? config("ai.providers.{$provider}.url"),
        ]);

        return self::PROVIDER_NAME;
    }

    /**
     * The configured model, or null for the provider's default model.
     */
    public function model(): ?string
    {
        return app(AiAvailability::class)->model();
    }

    /**
     * The request timeout in seconds.
     */
    public function timeout(): int
    {
        return (int) config('lunar.product-import.ai.timeout', 120);
    }

    /**
     * The answer structure.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $locale = $this->languages === []
            ? $schema->string()->nullable()
            : $schema->string()->enum($this->languages)->nullable();

        return [
            'columns' => $schema->array()->items($schema->object([
                'index' => $schema->integer()->min(0)->required(),
                'target' => $schema->string()->enum($this->targets)->required(),
                'confidence' => $schema->number()->min(0)->max(1)->required(),
                'locale' => $locale->required(),
                'option_name' => $schema->string()->nullable()->required(),
                'pair' => $schema->integer()->min(1)->nullable()->required(),
                'number_format' => $schema->object([
                    'decimal_separator' => $schema->string()->enum(['.', ','])->required(),
                    'thousands_separator' => $schema->string()->enum(self::THOUSANDS_SEPARATORS)->required(),
                ])->withoutAdditionalProperties()->nullable()->required(),
                'date_format' => $schema->string()->nullable()->required(),
                'values' => $schema->array()->items($schema->object([
                    'value' => $schema->string()->required(),
                    'meaning' => $schema->string()->enum(array_map(fn (ValueMeaning $meaning): string => $meaning->value, ValueMeaning::cases()))->required(),
                ])->withoutAdditionalProperties())->nullable()->required(),
                'item_parts' => $schema->array()->items(
                    $schema->string()->enum(array_map(fn (ItemPart $part): string => $part->value, ItemPart::cases())),
                )->nullable()->required(),
                'image_suffix' => $schema->string()->nullable()->required(),
                'list_separator' => $schema->string()->nullable()->required(),
                'path_separator' => $schema->string()->nullable()->required(),
                'item_separator' => $schema->string()->nullable()->required(),
            ])->withoutAdditionalProperties())->required(),
            'grouping' => $schema->object([
                'strategy' => $schema->string()->enum(array_map(fn (GroupingStrategy $strategy): string => $strategy->value, GroupingStrategy::cases()))->required(),
                'column' => $schema->integer()->nullable()->required(),
                'confidence' => $schema->number()->min(0)->max(1)->required(),
            ])->withoutAdditionalProperties()->required(),
            'units' => $schema->object([
                'weight_unit' => $this->unit($schema, $this->weightUnits)->required(),
                'length_unit' => $this->unit($schema, $this->lengthUnits)->required(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }

    /**
     * A unit of the answer: one of the store's unit codes, or null.
     *
     * @param  list<string>  $codes
     */
    protected function unit(JsonSchema $schema, array $codes): Type
    {
        return $codes === []
            ? $schema->string()->nullable()
            : $schema->string()->enum($codes)->nullable();
    }
}
