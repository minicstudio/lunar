<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\Attribute;
use Lunar\Models\Product;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Models\Tag;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\CategoryList;
use Lunar\ProductImport\Normalizing\SalePrice;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Taxonomy\ValueKeys;
use RuntimeException;
use Throwable;

/**
 * Writes one product group in one transaction: looks the variants up again
 * with a lock, restores a trashed product, creates or updates the product
 * with only its mapped, non-empty fields, adds its categories and the
 * additional collections without removing any, sets its URL slugs
 * (`ProductUrls`), attaches options, and creates or updates the variants and
 * their base price. Nothing is deleted. Search syncing is off while writing.
 */
class ProductUpserter
{
    /**
     * Attribute handles by id, resolved once.
     *
     * @var array<int, ?string>
     */
    protected array $attributeHandles = [];

    public function __construct(
        protected FieldValues $fields,
        protected ProductUrls $urls,
    ) {}

    /**
     * Write a group. Throws when the group cannot be written; nothing of it is kept then.
     */
    public function upsert(ProductImport $import, string $groupKey, CommitContext $context): void
    {
        Product::withoutSyncingToSearch(fn () => ProductVariant::withoutSyncingToSearch(
            fn () => DB::transaction(fn () => $this->write($import, $groupKey, $context))
        ));
    }

    /**
     * Mark the group's rows as failed with the reason.
     */
    public function markFailed(ProductImport $import, string $groupKey, Throwable $exception): void
    {
        $reason = __('lunarpanel.product-import::errors.group_failed', [
            'reason' => Str::limit($exception->getMessage(), 300),
        ]);

        $this->importableRows($import, $groupKey)->each(function (ProductImportRow $row) use ($reason): void {
            $row->forceFill([
                'status' => RowStatus::Failed,
                'messages' => [...($row->messages ?? []), ['level' => 'error', 'code' => 'commit_failed', 'field' => null, 'message' => $reason]],
            ])->save();
        });
    }

    /**
     * The rows of a group that are written.
     *
     * @return Collection<int, ProductImportRow>
     */
    public function importableRows(ProductImport $import, string $groupKey): Collection
    {
        return ProductImportRow::includedIn($import)
            ->where('group_key', $groupKey)
            ->whereIn('status', [RowStatus::Valid, RowStatus::Warning])
            ->orderBy('product_import_sheet_id')
            ->orderBy('row_number')
            ->get();
    }

    protected function write(ProductImport $import, string $groupKey, CommitContext $context): void
    {
        $rows = $this->importableRows($import, $groupKey);

        if ($rows->isEmpty()) {
            return;
        }

        $mapping = $context->mappings[$rows->first()->product_import_sheet_id];
        $matchColumn = $context->importOptions->matchKey->column();
        $matchField = $context->importOptions->matchKey->target()->value;

        /** @var array<int, NormalizedRow> $effective */
        $effective = $rows->mapWithKeys(fn (ProductImportRow $row): array => [$row->id => $row->effectiveRow()])->all();

        // Look the variants up again: one may have been created since the preview.
        // A product-only row (a variable product) has no variant of its own.
        $variants = [];

        foreach ($rows as $row) {
            $key = $row->isVariantRow() ? $effective[$row->id]->get($matchField) : null;

            $variants[$row->id] = filled($key)
                ? ProductVariant::withTrashed()->where($matchColumn, (string) $key)->orderByRaw('case when deleted_at is null then 0 else 1 end')->orderBy('id')->lockForUpdate()->first()
                : null;
        }

        $productIds = collect($variants)->filter()->pluck('product_id')->unique()->values();

        // Rows whose parent reference names an existing variant join its product.
        if ($productIds->isEmpty() && Str::startsWith($groupKey, 'product:')) {
            $productIds = collect([(int) Str::after($groupKey, 'product:')]);
        }

        if ($productIds->count() > 1) {
            throw new RuntimeException(__('lunarpanel.product-import::validation.messages.several_products', ['count' => $productIds->count()]));
        }

        $product = $productIds->isNotEmpty()
            ? Product::withTrashed()->lockForUpdate()->find($productIds->first())
            : null;

        $skipExisting = $context->importOptions->existingVariants === ExistingVariants::Skip;
        $writable = $rows->reject(fn (ProductImportRow $row): bool => $skipExisting && $variants[$row->id] !== null);

        // A product row whose variants are all left alone changes nothing either.
        if ($skipExisting && $rows->contains(fn (ProductImportRow $row): bool => $row->isVariantRow()) && ! $writable->contains(fn (ProductImportRow $row): bool => $row->isVariantRow())) {
            $writable = new Collection;
        }

        foreach ($rows->diff($writable) as $row) {
            $row->forceFill(['status' => RowStatus::Skipped, 'skip_reason' => RowSkipReason::Existing])->save();
        }

        if ($writable->isEmpty()) {
            return;
        }

        // The product row's values come first, then the variant rows' in file order.
        $ordered = $writable->sortBy(fn (ProductImportRow $row): int => $row->role !== null && $row->role->isProduct() ? 0 : 1)->values();

        $existingProductId = $product?->id;
        $product = $this->writeProduct($product, $ordered, $effective, $mapping, $context);

        $optionIds = $this->attachOptions($product, $mapping, $writable, $effective, $context);

        $written = [];

        foreach ($writable as $row) {
            $variant = $row->isVariantRow()
                ? $this->writeVariant($product, $variants[$row->id], $effective[$row->id], $context->mappings[$row->product_import_sheet_id] ?? $mapping, $optionIds, $context)
                : null;

            $written[$row->id] = $variant?->id;

            $row->forceFill([
                'status' => RowStatus::Imported,
                'action' => $variants[$row->id] !== null || ($variant === null && $existingProductId !== null) ? RowAction::Update : RowAction::Create,
                'matched_product_id' => $existingProductId,
                'matched_variant_id' => $variants[$row->id]?->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ])->save();
        }

        $this->recordImages($import, $product, $ordered, $effective, $written);
    }

    /**
     * Record the group's image URLs for downloading after the products are
     * written: the product's images in file order (the product row's first),
     * then each variant row's own images, linked to its variant. A URL the
     * import already recorded for the product is kept as it is.
     *
     * @param  Collection<int, ProductImportRow>  $ordered  The group's rows, the product row first.
     * @param  array<int, NormalizedRow>  $effective
     * @param  array<int, ?int>  $variantIds  Row id => written variant id.
     */
    protected function recordImages(ProductImport $import, Product $product, Collection $ordered, array $effective, array $variantIds): void
    {
        $position = 0;
        $seen = [];

        foreach ($ordered as $row) {
            foreach ((array) $effective[$row->id]->get(TargetField::ProductImages->value) as $url) {
                $this->recordImage($import, $product, $row, null, (string) $url, $position, $seen);
            }
        }

        foreach ($ordered as $row) {
            $variantId = $variantIds[$row->id] ?? null;

            if ($variantId === null) {
                continue;
            }

            foreach ((array) $effective[$row->id]->get(TargetField::VariantImage->value) as $url) {
                $this->recordImage($import, $product, $row, $variantId, (string) $url, $position, $seen);
            }
        }
    }

    /**
     * Record one image URL, unless it is not a web address or the group
     * already recorded it for the same product and variant.
     *
     * @param  array<string, true>  $seen
     */
    protected function recordImage(ProductImport $import, Product $product, ProductImportRow $row, ?int $variantId, string $url, int &$position, array &$seen): void
    {
        $url = Str::trim($url);
        $key = ($variantId ?? 0).'|'.$url;

        if (! Str::isUrl($url, ['http', 'https']) || Str::length($url) > 2048 || isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;

        ProductImportImage::query()->firstOrCreate([
            'product_import_id' => $import->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'url' => $url,
        ], [
            'product_import_row_id' => $row->id,
            'position' => $position++,
            'status' => ImageStatus::Pending,
        ]);
    }

    /**
     * Create the product, or restore and update the existing one with the
     * group's mapped, non-empty product fields.
     *
     * @param  Collection<int, ProductImportRow>  $ordered  The group's rows, the product row first.
     * @param  array<int, NormalizedRow>  $effective
     */
    protected function writeProduct(?Product $product, Collection $ordered, array $effective, SheetMapping $mapping, CommitContext $context): Product
    {
        $values = $ordered->map(fn (ProductImportRow $row): NormalizedRow => $effective[$row->id])->all();

        $creating = $product === null;
        $product ??= new Product([
            'product_type_id' => $mapping->productTypeId,
            'status' => $this->newProductStatus($values, $context),
        ]);

        if ($product->trashed()) {
            $product->restore();
        }

        $data = $product->attribute_data ?? collect();
        $attributes = $this->productAttributes($values);

        // Decided properties fill their attributes in the default language,
        // unless a mapped column already gives that value.
        foreach ($this->properties($values, $mapping, $context) as $handle => $value) {
            $attributes[$handle][$context->locale] ??= $value;
        }

        foreach ($attributes as $handle => $byLocale) {
            $data[$handle] = $this->fields->merge($this->fields->type(Product::morphName(), $handle), $data[$handle] ?? null, $byLocale);
        }

        $product->attribute_data = $data;

        $brand = $this->first($values, TargetField::ProductBrand->value);

        if (filled($brand) && ($brandId = $context->target(ValueType::Brand->value, '', ValueKeys::name((string) $brand))) !== null) {
            $product->brand_id = $brandId;
        }

        if ($creating || $product->isDirty()) {
            $product->save();
        }

        $collectionIds = array_values(array_unique([...$this->collectionIds($values, $context), ...$context->additionalCollectionIds]));

        // Without pivot data, memberships the product already has keep their position.
        if ($collectionIds !== []) {
            $product->collections()->syncWithoutDetaching($collectionIds);
        }

        $this->addTags($product, $values);

        $this->urls->write($product, $this->slugs($values), $creating);

        return $product;
    }

    /**
     * The group's URL slug in each language: the first one among its rows,
     * the product row's first.
     *
     * @param  list<NormalizedRow>  $values
     * @return array<string, string>
     */
    protected function slugs(array $values): array
    {
        $slugs = [];

        foreach ($values as $row) {
            foreach ($row->values as $field => $value) {
                if (filled($value) && Str::startsWith($field, TargetField::ProductSlug->value.'.')) {
                    $slugs[Str::after($field, TargetField::ProductSlug->value.'.')] ??= (string) $value;
                }
            }
        }

        return $slugs;
    }

    /**
     * Add the group's tags to the product without removing any it has.
     *
     * @param  list<NormalizedRow>  $values
     */
    protected function addTags(Product $product, array $values): void
    {
        $tags = [];

        foreach ($values as $row) {
            foreach ((array) $row->get(TargetField::ProductTags->value) as $tag) {
                $tag = Str::upper(Str::squish((string) $tag));

                if ($tag !== '') {
                    $tags[$tag] = true;
                }
            }
        }

        if ($tags === []) {
            return;
        }

        $ids = array_map(
            fn (string $tag): int => (int) Tag::query()->firstOrCreate(['value' => $tag])->id,
            array_keys($tags),
        );

        $product->tags()->syncWithoutDetaching($ids);
    }

    /**
     * The values of the group's properties that staff sent to an attribute,
     * by attribute handle. The first value of a property wins.
     *
     * @param  list<NormalizedRow>  $values
     * @return array<string, string>
     */
    protected function properties(array $values, SheetMapping $mapping, CommitContext $context): array
    {
        $properties = [];

        foreach ($values as $row) {
            foreach ((array) $row->get(RowStructurer::NAMED_PROPERTIES) as $property) {
                $name = (string) ($property['name'] ?? '');
                $value = (string) ($property['value'] ?? '');
                $attributeId = $name !== '' && $value !== ''
                    ? $context->target(ValueType::Property->value, ValueKeys::propertyType($mapping->productTypeId), ValueKeys::name($name))
                    : null;
                $handle = $attributeId !== null ? $this->attributeHandle($attributeId) : null;

                if ($handle !== null) {
                    $properties[$handle] ??= $value;
                }
            }
        }

        return $properties;
    }

    /**
     * The handle of a product attribute, resolved once.
     */
    protected function attributeHandle(int $attributeId): ?string
    {
        if (! array_key_exists($attributeId, $this->attributeHandles)) {
            $this->attributeHandles[$attributeId] = Attribute::query()
                ->whereKey($attributeId)
                ->where('attribute_type', Product::morphName())
                ->value('handle');
        }

        return $this->attributeHandles[$attributeId];
    }

    /**
     * The status of a product the group creates: the status option, or with
     * the status column option the group's first status value, else draft.
     *
     * @param  list<NormalizedRow>  $values
     */
    protected function newProductStatus(array $values, CommitContext $context): string
    {
        return match ($context->importOptions->newProductStatus) {
            NewProductStatus::Published => 'published',
            NewProductStatus::FromColumn => (string) ($this->first($values, TargetField::ProductStatus->value) ?? 'draft'),
            default => 'draft',
        };
    }

    /**
     * The product attribute values of the group, by handle and locale. The
     * first non-empty value of the group wins.
     *
     * @param  list<NormalizedRow>  $values
     * @return array<string, array<string, mixed>>
     */
    protected function productAttributes(array $values): array
    {
        $attributes = [];

        foreach ($values as $row) {
            foreach ($row->values as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                [$handle, $locale] = match (true) {
                    Str::startsWith($field, TargetField::ProductName->value.'.') => ['name', Str::after($field, TargetField::ProductName->value.'.')],
                    Str::startsWith($field, TargetField::ProductDescription->value.'.') => ['description', Str::after($field, TargetField::ProductDescription->value.'.')],
                    Str::startsWith($field, TargetField::ATTRIBUTE_PREFIX) => [Str::before(TargetField::attributeHandle($field), '.'), Str::contains(TargetField::attributeHandle($field), '.') ? Str::after(TargetField::attributeHandle($field), '.') : ''],
                    default => [null, null],
                };

                if ($handle !== null) {
                    $attributes[$handle][$locale] ??= $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * The collections of the categories of all the group's rows, as decided
     * in the review.
     *
     * @param  list<NormalizedRow>  $values
     * @return list<int>
     */
    protected function collectionIds(array $values, CommitContext $context): array
    {
        $ids = [];

        foreach ($values as $row) {
            foreach (CategoryList::paths($row->get(TargetField::ProductCategory->value)) as $path) {
                if (($id = $context->target(ValueType::Category->value, '', ValueKeys::category($path))) !== null) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * Attach the options the group's rows use, with their position: the
     * mapped option columns, then the options named in cells, unless staff
     * skipped the name.
     *
     * @param  Collection<int, ProductImportRow>  $rows
     * @param  array<int, NormalizedRow>  $effective
     * @return array<string, int> Option key => option id.
     */
    protected function attachOptions(Product $product, SheetMapping $mapping, Collection $rows, array $effective, CommitContext $context): array
    {
        $optionIds = [];
        $attach = [];
        $position = 1;

        $keys = [];

        foreach ($rows as $row) {
            foreach (($context->mappings[$row->product_import_sheet_id] ?? $mapping)->optionKeys() as $key) {
                $keys[$key] = true;
            }
        }

        foreach (array_keys($keys) as $key) {
            $optionId = $context->options[$key] ?? null;

            if ($optionId === null) {
                continue;
            }

            $optionIds[$key] = $optionId;

            $used = $rows->contains(fn (ProductImportRow $row): bool => filled($effective[$row->id]->get('variant.option.'.$key)));

            if ($used && ! isset($attach[$optionId])) {
                $attach[$optionId] = ['position' => $position++];
            }
        }

        foreach ($rows as $row) {
            foreach (array_keys($effective[$row->id]->namedOptions()) as $key) {
                $optionId = $context->options[$key] ?? null;

                if ($optionId === null || isset($optionIds[$key])) {
                    continue;
                }

                $optionIds[$key] = $optionId;
                $attach[$optionId] ??= ['position' => $position++];
            }
        }

        if ($attach !== []) {
            $product->productOptions()->syncWithoutDetaching($attach);
        }

        return $optionIds;
    }

    /**
     * Create the variant, or restore and update the existing one with the
     * row's mapped, non-empty fields, then its option values and base price.
     *
     * @param  array<string, int>  $optionIds
     */
    protected function writeVariant(Product $product, ?ProductVariant $variant, NormalizedRow $row, SheetMapping $mapping, array $optionIds, CommitContext $context): ProductVariant
    {
        $attributes = array_filter([
            'sku' => $row->get(TargetField::VariantSku->value),
            'ean' => $row->get(TargetField::VariantEan->value),
            'gtin' => $row->get(TargetField::VariantGtin->value),
            'mpn' => $row->get(TargetField::VariantMpn->value),
            'stock' => $row->get(TargetField::VariantStock->value),
            'min_quantity' => $row->get(TargetField::VariantMinQuantity->value),
            'shippable' => $row->get(TargetField::VariantShippable->value),
            'weight_value' => $row->get(TargetField::VariantWeight->value),
            'length_value' => $row->get(TargetField::VariantLength->value),
            'width_value' => $row->get(TargetField::VariantWidth->value),
            'height_value' => $row->get(TargetField::VariantHeight->value),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $weightUnit = $row->get(RowStructurer::WEIGHT_UNIT) ?? $mapping->weightUnit;
        $lengthUnit = $row->get(RowStructurer::LENGTH_UNIT) ?? $mapping->lengthUnit;

        if (isset($attributes['weight_value']) && filled($weightUnit)) {
            $attributes['weight_unit'] = $weightUnit;
        }

        foreach (['length', 'width', 'height'] as $dimension) {
            if (isset($attributes[$dimension.'_value']) && filled($lengthUnit)) {
                $attributes[$dimension.'_unit'] = $lengthUnit;
            }
        }

        $attributes = [...$attributes, ...$this->purchasability($row)];

        if ($variant === null) {
            $variant = $product->variants()->create([
                'tax_class_id' => $context->taxClassId,
                'unit_quantity' => 1,
                'shippable' => true,
                'purchasable' => 'always',
                'stock' => 0,
                ...$attributes,
            ]);
        } else {
            if ($variant->trashed()) {
                $variant->restore();
            }

            $variant->fill($attributes);

            if ($variant->isDirty()) {
                $variant->save();
            }
        }

        $this->writeOptionValues($variant, $row, $optionIds, $context);

        $prices = SalePrice::for($row);

        // A row that writes a price writes its compare price with it.
        if ($prices->price !== null) {
            $variant->prices()->updateOrCreate(
                ['currency_id' => $context->currency->id, 'min_quantity' => 1, 'customer_group_id' => null],
                ['price' => $prices->price, 'compare_price' => $prices->comparePrice],
            );
        }

        return $variant;
    }

    /**
     * How the variant can be bought, when the row fills its stock status or
     * backorders: allowed backorders or a stock status on backorder allow
     * buying on backorder; otherwise a filled stock allows buying in stock,
     * in stock without a stock number always, and out of stock without a
     * stock number in stock, with stock 0.
     *
     * @return array<string, mixed>
     */
    protected function purchasability(NormalizedRow $row): array
    {
        $status = $row->get(TargetField::VariantStockStatus->value);
        $backorders = $row->get(TargetField::VariantBackorders->value);

        if ($status === null && $backorders === null) {
            return [];
        }

        return match (true) {
            $backorders === true || $status === 'on_backorder' => ['purchasable' => 'in_stock_or_on_backorder'],
            $row->filled(TargetField::VariantStock->value) => ['purchasable' => 'in_stock'],
            $status === 'in_stock' => ['purchasable' => 'always'],
            $status === 'out_of_stock' => ['purchasable' => 'in_stock', 'stock' => 0],
            default => [],
        };
    }

    /**
     * Give the variant the row's value of each option, replacing its earlier
     * value of that option.
     *
     * @param  array<string, int>  $optionIds
     */
    protected function writeOptionValues(ProductVariant $variant, NormalizedRow $row, array $optionIds, CommitContext $context): void
    {
        $named = $row->namedOptions();

        foreach ($optionIds as $key => $optionId) {
            $value = Str::startsWith($key, 'name:') ? ($named[$key] ?? null) : $row->get('variant.option.'.$key);

            if (! filled($value)) {
                continue;
            }

            $valueId = $context->target(ValueType::OptionValue->value, $key, ValueKeys::name((string) $value));

            if ($valueId === null) {
                throw new RuntimeException("The option value \"{$value}\" has no record.");
            }

            $current = $variant->values()->where('product_option_id', $optionId)->pluck((new ProductOptionValue)->getTable().'.id')->all();
            $detach = array_diff($current, [$valueId]);

            if ($detach !== []) {
                $variant->values()->detach($detach);
            }

            if (! in_array($valueId, $current, true)) {
                $variant->values()->attach($valueId);
            }
        }
    }

    /**
     * The first non-empty value of a field in the group.
     *
     * @param  list<NormalizedRow>  $values
     */
    protected function first(array $values, string $field): mixed
    {
        foreach ($values as $row) {
            if ($row->filled($field)) {
                return $row->get($field);
            }
        }

        return null;
    }
}
