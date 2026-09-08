<?php

namespace Lunar\ProductFeed\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lunar\Base\DiscountManagerInterface;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class ProductFeedService
{
    public function __construct(
        protected DiscountManagerInterface $discountManager,
    ) {}

    /**
     * Build feed attribute arrays for eligible catalog variants.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function buildItems(): iterable
    {
        $chunkSize = max(1, (int) config('lunar.product-feed.query.chunk_size', 100));

        $products = $this->scopeToDefaultChannelAndCustomerGroup(Product::query())
            ->with($this->feedEagerLoads())
            ->lazyById($chunkSize);

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                if (! $this->isVariantFundamentallyPurchasable($variant)) {
                    continue;
                }

                $item = $this->mapVariant($product, $variant);

                if ($item !== null) {
                    yield $item;
                }
            }
        }
    }

    /**
     * Map a purchasable variant to feed attributes, or null when price/link cannot be resolved.
     *
     * Google CSV/XML fields are flat scalars. Lunar-only extras (`discount_name`,
     * `collections`, `product_options`, `review_score`, `tags`) are included for JSON
     * (and `discount_name` also for CSV); XML encoders must omit non-Google keys.
     *
     * @return array<string, mixed>|null
     */
    public function mapVariant(Product $product, ProductVariant $variant): ?array
    {
        $currency = Currency::getDefault();

        if (! $currency) {
            return null;
        }

        $originalPrice = $variant->getOriginalPricesIncTax()
            ->filter(fn (PriceDataType $price) => $price->currency->code === $currency->code)
            ->first();

        if (! $originalPrice) {
            return null;
        }

        $link = $this->resolveProductLink($product);

        if ($link === null) {
            return null;
        }

        $title = trim((string) ($product->translateAttribute('name') ?? "Product {$product->id}"));
        $description = trim(strip_tags((string) ($product->translateAttribute('description') ?? '')));

        if ($description === '') {
            $description = $title;
        }

        $sku = trim((string) ($variant->sku ?? ''));
        $id = $sku !== ''
            ? str_replace('/', '-', $sku)
            : 'variant-'.$variant->id;

        $item = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'availability' => $variant->canBeFulfilledAtQuantity(1) ? 'in_stock' : 'out_of_stock',
            'condition' => 'new',
            'price' => $this->formatFeedPrice($originalPrice, $currency),
            'link' => $link,
            'item_group_id' => (string) $product->id,
        ];

        $currentPrice = $variant->getCurrentPricesIncTax()
            ->filter(fn (PriceDataType $price) => $price->currency->code === $currency->code)
            ->first();

        $discount = null;

        if ($currentPrice && $currentPrice->value < $originalPrice->value) {
            $item['sale_price'] = $this->formatFeedPrice($currentPrice, $currency);
            $discount = $this->discountManager->getDiscountForPurchasable($variant);

            $discountName = trim((string) ($discount?->name ?? ''));

            if ($discountName !== '') {
                $item['discount_name'] = $discountName;
            }

            $effectiveDate = $this->formatSalePriceEffectiveDate($discount);

            if ($effectiveDate !== null) {
                $item['sale_price_effective_date'] = $effectiveDate;
            }
        }

        $imageLink = $this->resolveImageLink($product);

        if ($imageLink !== null) {
            $item['image_link'] = $imageLink;
        }

        $brandName = $product->brand instanceof Brand
            ? trim((string) $product->brand->name)
            : '';

        if ($brandName !== '') {
            $item['brand'] = $brandName;
        }

        $gtin = trim((string) ($variant->gtin ?: $variant->ean ?: ''));

        if ($gtin !== '') {
            $item['gtin'] = $gtin;
        }

        $mpn = trim((string) ($variant->mpn ?? ''));

        if ($mpn !== '') {
            $item['mpn'] = $mpn;
        }

        $productType = $this->resolveProductType($product);

        if ($productType !== null) {
            $item['product_type'] = $productType;
        }

        $optionMap = $this->resolveProductOptionsMap($variant);

        foreach ($optionMap as $optionName => $optionValue) {
            $googleAttribute = $this->mapOptionNameToGoogleAttribute($optionName);

            if ($googleAttribute !== null) {
                $item[$googleAttribute] = $optionValue;
            }
        }

        if ($optionMap !== []) {
            $item['product_options'] = $optionMap;
        }

        $collectionNames = $this->resolveCollectionNames($product);

        if ($collectionNames !== []) {
            $item['collections'] = $collectionNames;
        }

        $reviewScore = $this->resolveReviewScore($product);

        if ($reviewScore !== null) {
            $item['review_score'] = $reviewScore;
        }

        $tags = $this->resolveTags($product);

        if ($tags !== []) {
            $item['tags'] = $tags;
        }

        return $item;
    }

    /**
     * @return list<string>
     */
    protected function feedEagerLoads(): array
    {
        $relations = [
            'variants.prices.currency',
            'variants.values.option',
            'brand',
            'media',
            'tags',
            'collections.ancestors',
            'defaultUrl.language',
            'localeUrl.language',
        ];

        if (Product::hasMacro('getRatingAverage')) {
            $relations[] = 'variants.reviews';
        }

        return $relations;
    }

    protected function formatFeedPrice(PriceDataType $price, Currency $currency): string
    {
        return sprintf(
            '%s %s',
            number_format($price->decimal(), $currency->decimal_places, '.', ''),
            $currency->code
        );
    }

    protected function formatSalePriceEffectiveDate(?Discount $discount): ?string
    {
        if (! $discount?->starts_at) {
            return null;
        }

        $start = $discount->starts_at->toIso8601String();
        // Google requires start/end; open-ended Lunar discounts use start + 10 years.
        $end = ($discount->ends_at ?? $discount->starts_at->copy()->addYears(10))->toIso8601String();

        return $start.'/'.$end;
    }

    /**
     * Google `product_type`: main (root) collection name only.
     */
    protected function resolveProductType(Product $product): ?string
    {
        $collection = $product->collections->first();

        if (! $collection) {
            return null;
        }

        $main = $collection->ancestors->first() ?? $collection;
        $name = trim((string) ($main->translateAttribute('name') ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @return list<string>
     */
    protected function resolveCollectionNames(Product $product): array
    {
        return $product->collections
            ->map(fn ($collection) => trim((string) ($collection->translateAttribute('name') ?? '')))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function resolveProductOptionsMap(ProductVariant $variant): array
    {
        $options = [];

        foreach ($variant->values as $value) {
            $optionName = trim((string) ($value->option?->translate('name') ?? ''));
            $valueName = trim((string) ($value->translate('name') ?? ''));

            if ($optionName === '' || $valueName === '') {
                continue;
            }

            $options[$optionName] = $valueName;
        }

        return $options;
    }

    protected function mapOptionNameToGoogleAttribute(string $optionName): ?string
    {
        $normalized = Str::lower($optionName);

        if (str_contains($normalized, 'color') || str_contains($normalized, 'colour')) {
            return 'color';
        }

        if (str_contains($normalized, 'size')) {
            return 'size';
        }

        if (str_contains($normalized, 'material')) {
            return 'material';
        }

        if (str_contains($normalized, 'pattern')) {
            return 'pattern';
        }

        return null;
    }

    protected function resolveReviewScore(Product $product): ?string
    {
        if (! is_callable([$product, 'getRatingAverage'])) {
            return null;
        }

        $average = (float) $product->getRatingAverage();

        if ($average <= 0) {
            return null;
        }

        return number_format($average, 1, '.', '');
    }

    /**
     * @return list<string>
     */
    protected function resolveTags(Product $product): array
    {
        return $product->tags
            ->map(fn ($tag) => trim((string) ($tag->value ?? '')))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Scope to published, storefront-eligible products for the default channel
     * and customer group.
     *
     * Deliberately avoids `Product::available()`/`purchasableCustomerGroups()`,
     * which read from `StorefrontSession`: that facade persists into the
     * request's session, which would let a public, cacheable feed request
     * silently overwrite a real visitor's storefront channel/customer group.
     * The default channel/customer group are queried directly instead.
     */
    protected function scopeToDefaultChannelAndCustomerGroup(Builder $query): Builder
    {
        $query->status('published');

        if ($channel = Channel::getDefault()) {
            $query->channel($channel);
        }

        if ($customerGroup = CustomerGroup::getDefault()) {
            $query
                ->whereHas('customerGroups', function (Builder $query) use ($customerGroup): void {
                    $query->where('lunar_customer_groups.id', $customerGroup->id)
                        ->where('visible', true)
                        ->where('enabled', true)
                        ->where(function (Builder $query): void {
                            $query->whereNull('starts_at')
                                ->orWhere('starts_at', '<=', now());
                        })
                        ->where(function (Builder $query): void {
                            $query->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', now());
                        });
                })
                ->whereHas('customerGroups', function (Builder $query) use ($customerGroup): void {
                    $query->where('lunar_customer_groups.id', $customerGroup->id)
                        ->where('purchasable', true)
                        ->where(function (Builder $query): void {
                            $query->whereNull('starts_at')
                                ->orWhere('starts_at', '<=', now());
                        })
                        ->where(function (Builder $query): void {
                            $query->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', now());
                        });
                });
        }

        return $query;
    }

    protected function isVariantFundamentallyPurchasable(ProductVariant $variant): bool
    {
        return $variant->purchasable !== 'out_of_stock';
    }

    protected function resolveProductLink(Product $product): ?string
    {
        $slug = $product->defaultUrl?->slug
            ?? $product->localeUrl?->slug;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $namedRoute = config('lunar.product-feed.product_url.route');

        if (is_string($namedRoute) && $namedRoute !== '') {
            return route($namedRoute, ['slug' => $slug], absolute: true);
        }

        $template = (string) config(
            'lunar.product-feed.product_url.template',
            '{base_url}/{slug}'
        );
        $baseUrl = rtrim((string) config(
            'lunar.product-feed.product_url.base_url',
            config('app.url')
        ), '/');

        $url = str_replace(
            ['{base_url}', '{slug}'],
            [$baseUrl, $slug],
            $template
        );

        return $url !== '' ? $url : null;
    }

    protected function resolveImageLink(Product $product): ?string
    {
        $media = $product->getMedia(
            config('lunar.media.collection', 'images'),
            ['primary' => true]
        )->first() ?? $product->getMedia(config('lunar.media.collection', 'images'))->first();

        if (! $media) {
            return null;
        }

        $url = $media->getUrl('large') ?: $media->getUrl();

        return is_string($url) && $url !== '' ? $url : null;
    }
}
