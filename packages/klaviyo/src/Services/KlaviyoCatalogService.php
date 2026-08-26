<?php

namespace Lunar\Klaviyo\Services;

use Lunar\Exceptions\SilentException;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Requests\CreateCatalogCategoryRequest;
use Lunar\Klaviyo\Requests\CreateCatalogItemRequest;
use Lunar\Klaviyo\Requests\CreateCatalogVariantRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogItemRequest;
use Lunar\Klaviyo\Requests\UpdateCatalogItemRequest;
use Lunar\Klaviyo\Requests\UpdateCatalogVariantRequest;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Saloon\Http\Response;

class KlaviyoCatalogService
{
    public function __construct(protected KlaviyoService $klaviyo) {}

    /**
     * Upsert a product as a Klaviyo catalog item + variants.
     * Unavailable / unpublished products are deleted from the remote catalog.
     *
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function syncProduct(Product $product): array
    {
        $product->loadMissing(['variants', 'collections', 'brand', 'media']);

        $isPublished = $product->status === 'published';
        $isAvailable = null;

        if ($isPublished) {
            try {
                $isAvailable = $product->isAvailable();
            } catch (\Throwable $e) {
                $isAvailable = false;
                KlaviyoLogger::warning('Catalog isAvailable() check failed — treating as unavailable', [
                    'product_id' => $product->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        KlaviyoLogger::info('Catalog sync started', [
            'product_id' => $product->id,
            'status' => $product->status,
            'is_published' => $isPublished,
            'is_available' => $isAvailable,
            'variant_count' => $product->variants->count(),
            'collection_count' => $product->collections->count(),
            'title' => $product->translateAttribute('name'),
        ]);

        if (! $isPublished || $isAvailable !== true) {
            KlaviyoLogger::warning('Catalog sync will DELETE remote item (not published or not available)', [
                'product_id' => $product->id,
                'status' => $product->status,
                'is_published' => $isPublished,
                'is_available' => $isAvailable,
                'reason' => ! $isPublished ? 'not_published' : 'not_available',
            ]);

            $this->deleteProduct($product);

            return [];
        }

        $categoryIds = $this->ensureCategoriesForProduct($product);
        $item = $this->upsertCatalogItem($product, $categoryIds);

        $variantResults = [];
        foreach ($product->variants as $variant) {
            $variantResults[] = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'result' => $this->upsertCatalogVariant($product, $variant) ? 'ok' : 'failed',
            ];
        }

        $catalogItemId = $item['data']['id'] ?? $this->compoundId($this->resolveItemExternalId($product));

        KlaviyoLogger::info('Catalog sync completed', [
            'product_id' => $product->id,
            'item_external_id' => $this->resolveItemExternalId($product),
            'klaviyo_catalog_item_id' => $catalogItemId,
            'category_ids' => $categoryIds,
            'variant_count' => $product->variants->count(),
            'variants' => $variantResults,
        ]);

        return $item;
    }

    /**
     * Delete a catalog item (variants are removed with the item).
     *
     * @throws FailedKlaviyoSyncException
     */
    public function deleteProduct(Product $product): bool
    {
        $product->loadMissing(['variants']);

        $externalIds = array_values(array_unique(array_filter([
            $this->resolveItemExternalId($product),
            // Clean up legacy items that used product id as external_id.
            (string) $product->id,
        ])));

        $allOk = true;

        foreach ($externalIds as $externalId) {
            $catalogItemId = $this->compoundId($externalId);

            KlaviyoLogger::info('Catalog delete started', [
                'product_id' => $product->id,
                'item_external_id' => $externalId,
                'klaviyo_catalog_item_id' => $catalogItemId,
            ]);

            $response = $this->klaviyo->getConnector()->send(new DeleteCatalogItemRequest($catalogItemId));

            if ($response->successful() || $response->status() === 404) {
                KlaviyoLogger::info('Catalog delete finished', [
                    'product_id' => $product->id,
                    'item_external_id' => $externalId,
                    'klaviyo_catalog_item_id' => $catalogItemId,
                    'http_status' => $response->status(),
                    'already_absent' => $response->status() === 404,
                ]);

                continue;
            }

            $allOk = false;

            KlaviyoLogger::error('Catalog delete API failed', [
                'product_id' => $product->id,
                'item_external_id' => $externalId,
                'klaviyo_catalog_item_id' => $catalogItemId,
                'http_status' => $response->status(),
                'body' => $this->truncateBody($response->body()),
            ]);

            throw new FailedKlaviyoSyncException(
                "Failed to delete Klaviyo catalog item for product {$product->id}: {$response->body()}"
            );
        }

        return $allOk;
    }

    /**
     * Ensure a catalog category exists; treat duplicate/409 as success.
     *
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function ensureCategory(string $externalId, string $name): array
    {
        $externalId = $this->sanitizeCategoryExternalId($externalId);

        $payload = [
            'data' => [
                'type' => 'catalog-category',
                'attributes' => [
                    'external_id' => $externalId,
                    'name' => $name !== '' ? $name : $externalId,
                    'integration_type' => '$custom',
                    'catalog_type' => '$default',
                ],
                'relationships' => [
                    'items' => [
                        'data' => [],
                    ],
                ],
            ],
        ];

        $response = $this->klaviyo->getConnector()->send(new CreateCatalogCategoryRequest($payload));

        if ($response->successful() || $this->isDuplicateConflict($response)) {
            $compoundId = $this->compoundId($externalId);

            KlaviyoLogger::info('Catalog category ensured', [
                'external_id' => $externalId,
                'name' => $name,
                'klaviyo_category_id' => $compoundId,
                'http_status' => $response->status(),
                'already_existed' => $this->isDuplicateConflict($response),
            ]);

            return [
                'external_id' => $externalId,
                'id' => $compoundId,
            ];
        }

        KlaviyoLogger::error('Catalog category API failed', [
            'external_id' => $externalId,
            'http_status' => $response->status(),
            'body' => $this->truncateBody($response->body()),
        ]);

        throw new FailedKlaviyoSyncException(
            "Failed to ensure Klaviyo catalog category {$externalId}: {$response->body()}"
        );
    }

    protected function shouldSyncProduct(Product $product): bool
    {
        if ($product->status !== 'published') {
            return false;
        }

        try {
            return $product->isAvailable();
        } catch (\Throwable $e) {
            KlaviyoLogger::warning('Catalog shouldSyncProduct isAvailable() failed', [
                'product_id' => $product->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return list<string> Compound category IDs for item relationships
     *
     * @throws FailedKlaviyoSyncException
     */
    protected function ensureCategoriesForProduct(Product $product): array
    {
        $categories = [];

        foreach ($product->collections as $collection) {
            $externalId = (string) $collection->id;
            $name = $collection->translateAttribute('name') ?? "Collection {$collection->id}";
            $ensured = $this->ensureCategory($externalId, $name);
            $categories[] = $ensured['id'];
        }

        if ($categories === []) {
            $defaultExternalId = (string) config(
                'lunar.klaviyo.catalog.default_category_external_id',
                'uncategorized'
            );
            $ensured = $this->ensureCategory($defaultExternalId, 'Uncategorized');
            $categories[] = $ensured['id'];

            KlaviyoLogger::info('Catalog using default category (product has no collections)', [
                'product_id' => $product->id,
                'default_category_external_id' => $defaultExternalId,
            ]);
        }

        return $categories;
    }

    /**
     * @param  list<string>  $categoryCompoundIds
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    protected function upsertCatalogItem(Product $product, array $categoryCompoundIds): array
    {
        $externalId = $this->resolveItemExternalId($product);
        $title = $product->translateAttribute('name') ?? "Product {$product->id}";
        $description = strip_tags((string) ($product->translateAttribute('description') ?? $title));
        $url = $this->resolveProductUrl($product);
        $imageUrl = $product->getMedia('images', ['primary' => true])->first()?->getUrl('large');

        $attributes = [
            'external_id' => $externalId,
            'title' => $title,
            'description' => $description !== '' ? $description : $title,
            'url' => $url,
            'integration_type' => '$custom',
            'catalog_type' => '$default',
            'published' => true,
        ];

        if ($imageUrl) {
            $attributes['image_full_url'] = $imageUrl;
            $attributes['image_thumbnail_url'] = $imageUrl;
            $attributes['images'] = [$imageUrl];
        }

        $createPayload = [
            'data' => [
                'type' => 'catalog-item',
                'attributes' => $attributes,
                'relationships' => [
                    'categories' => [
                        'data' => array_map(
                            fn (string $id) => [
                                'type' => 'catalog-category',
                                'id' => $id,
                            ],
                            $categoryCompoundIds
                        ),
                    ],
                ],
            ],
        ];

        KlaviyoLogger::info('Catalog item create attempt', [
            'product_id' => $product->id,
            'external_id' => $externalId,
            'external_id_source' => $externalId === (string) $product->id ? 'product_id_fallback' : 'sku',
            'title' => $title,
            'url' => $url,
            'has_image' => (bool) $imageUrl,
            'category_ids' => $categoryCompoundIds,
        ]);

        $response = $this->klaviyo->getConnector()->send(new CreateCatalogItemRequest($createPayload));

        if ($response->successful()) {
            $json = $response->json() ?? [];

            KlaviyoLogger::info('Catalog item created', [
                'product_id' => $product->id,
                'http_status' => $response->status(),
                'klaviyo_catalog_item_id' => $json['data']['id'] ?? $this->compoundId($externalId),
            ]);

            return $json;
        }

        if ($this->isDuplicateConflict($response)) {
            KlaviyoLogger::info('Catalog item already exists — updating', [
                'product_id' => $product->id,
                'create_http_status' => $response->status(),
                'create_body' => $this->truncateBody($response->body()),
            ]);

            $updatePayload = [
                'data' => [
                    'type' => 'catalog-item',
                    'id' => $this->compoundId($externalId),
                    'attributes' => collect($attributes)
                        ->except(['external_id', 'integration_type', 'catalog_type'])
                        ->all(),
                    'relationships' => $createPayload['data']['relationships'],
                ],
            ];

            $updateResponse = $this->klaviyo->getConnector()->send(
                new UpdateCatalogItemRequest($this->compoundId($externalId), $updatePayload)
            );

            if ($updateResponse->successful()) {
                $json = $updateResponse->json() ?? [];

                KlaviyoLogger::info('Catalog item updated', [
                    'product_id' => $product->id,
                    'http_status' => $updateResponse->status(),
                    'klaviyo_catalog_item_id' => $json['data']['id'] ?? $this->compoundId($externalId),
                ]);

                return $json;
            }

            KlaviyoLogger::error('Catalog item update API failed', [
                'product_id' => $product->id,
                'http_status' => $updateResponse->status(),
                'body' => $this->truncateBody($updateResponse->body()),
            ]);

            throw new FailedKlaviyoSyncException(
                "Failed to update Klaviyo catalog item for product {$product->id}: {$updateResponse->body()}"
            );
        }

        KlaviyoLogger::error('Catalog item create API failed', [
            'product_id' => $product->id,
            'http_status' => $response->status(),
            'body' => $this->truncateBody($response->body()),
        ]);

        throw new FailedKlaviyoSyncException(
            "Failed to create Klaviyo catalog item for product {$product->id}: {$response->body()}"
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    protected function upsertCatalogVariant(Product $product, ProductVariant $variant): array
    {
        $externalId = (string) $variant->id;
        $title = $product->translateAttribute('name') ?? "Product {$product->id}";
        $description = strip_tags((string) ($product->translateAttribute('description') ?? $title));
        $url = $this->resolveProductUrl($product);
        $imageUrl = $product->getMedia('images', ['primary' => true])->first()?->getUrl('large');
        $defaultCurrency = Currency::getDefault();

        $price = $variant->getCurrentPricesIncTax()
            ->filter(fn ($price) => $price->currency->code === $defaultCurrency?->code)
            ->first();

        $attributes = [
            'external_id' => $externalId,
            'title' => $title,
            'description' => $description !== '' ? $description : $title,
            'sku' => $variant->sku ?? (string) $variant->id,
            'inventory_quantity' => (float) ($variant->stock ?? 0),
            'price' => $price ? $price->value / 100 : 0,
            'url' => $url,
            'published' => true,
        ];

        if ($imageUrl) {
            $attributes['image_full_url'] = $imageUrl;
            $attributes['image_thumbnail_url'] = $imageUrl;
            $attributes['images'] = [$imageUrl];
        }

        $createPayload = [
            'data' => [
                'type' => 'catalog-variant',
                'attributes' => $attributes,
                'relationships' => [
                    'item' => [
                        'data' => [
                            'type' => 'catalog-item',
                            'id' => $this->compoundId($this->resolveItemExternalId($product)),
                        ],
                    ],
                ],
            ],
        ];

        KlaviyoLogger::info('Catalog variant create attempt', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'external_id' => $externalId,
            'sku' => $attributes['sku'],
            'price' => $attributes['price'],
            'inventory_quantity' => $attributes['inventory_quantity'],
        ]);

        $response = $this->klaviyo->getConnector()->send(new CreateCatalogVariantRequest($createPayload));

        if ($response->successful()) {
            $json = $response->json() ?? [];

            KlaviyoLogger::info('Catalog variant created', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'http_status' => $response->status(),
                'klaviyo_catalog_variant_id' => $json['data']['id'] ?? $this->compoundId($externalId),
            ]);

            return $json;
        }

        if ($this->isDuplicateConflict($response)) {
            KlaviyoLogger::info('Catalog variant already exists — updating', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'create_http_status' => $response->status(),
                'create_body' => $this->truncateBody($response->body()),
            ]);

            $updatePayload = [
                'data' => [
                    'type' => 'catalog-variant',
                    'id' => $this->compoundId($externalId),
                    'attributes' => collect($attributes)
                        ->except(['external_id'])
                        ->all(),
                ],
            ];

            $updateResponse = $this->klaviyo->getConnector()->send(
                new UpdateCatalogVariantRequest($this->compoundId($externalId), $updatePayload)
            );

            if ($updateResponse->successful()) {
                $json = $updateResponse->json() ?? [];

                KlaviyoLogger::info('Catalog variant updated', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'http_status' => $updateResponse->status(),
                    'klaviyo_catalog_variant_id' => $json['data']['id'] ?? $this->compoundId($externalId),
                ]);

                return $json;
            }

            KlaviyoLogger::error('Catalog variant update API failed', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'http_status' => $updateResponse->status(),
                'body' => $this->truncateBody($updateResponse->body()),
            ]);

            throw new FailedKlaviyoSyncException(
                "Failed to update Klaviyo catalog variant {$variant->id}: {$updateResponse->body()}"
            );
        }

        KlaviyoLogger::error('Catalog variant create API failed', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'http_status' => $response->status(),
            'body' => $this->truncateBody($response->body()),
        ]);

        throw new FailedKlaviyoSyncException(
            "Failed to create Klaviyo catalog variant {$variant->id}: {$response->body()}"
        );
    }

    protected function resolveProductUrl(Product $product): string
    {
        $slug = $product->localeUrl()?->first()?->slug;
        $url = config('app.url').'/'.($slug ?? '');

        if (! $slug) {
            KlaviyoLogger::warning('Catalog product has no storefront slug', [
                'product_id' => $product->id,
                'fallback_url' => $url,
            ]);

            report(new SilentException(
                "Product {$product->id} has no URL and may not sync cleanly to Klaviyo catalog."
            ));
        }

        return rtrim($url, '/') ?: (string) config('app.url');
    }

    /**
     * Catalog item external_id: prefer the first non-empty variant SKU.
     * Falls back to product id when no SKU exists. Must not contain `/`.
     */
    protected function resolveItemExternalId(Product $product): string
    {
        $product->loadMissing(['variants']);

        foreach ($product->variants as $variant) {
            $sku = trim((string) ($variant->sku ?? ''));

            if ($sku === '') {
                continue;
            }

            $sku = str_replace('/', '-', $sku);

            if ($sku !== '') {
                return $sku;
            }
        }

        KlaviyoLogger::warning('Catalog item has no variant SKU — falling back to product id', [
            'product_id' => $product->id,
        ]);

        return (string) $product->id;
    }

    protected function compoundId(string $externalId): string
    {
        return '$custom:::$default:::'.$externalId;
    }

    /**
     * Klaviyo strips special characters from category external IDs.
     */
    protected function sanitizeCategoryExternalId(string $externalId): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $externalId) ?? '';

        return $sanitized !== '' ? $sanitized : 'uncategorized';
    }

    protected function isDuplicateConflict(Response $response): bool
    {
        if ($response->status() === 409) {
            return true;
        }

        $body = strtolower($response->body());

        return $response->status() === 400
            && (str_contains($body, 'duplicate') || str_contains($body, 'already exists'));
    }

    protected function truncateBody(string $body, int $max = 2000): string
    {
        if (strlen($body) <= $max) {
            return $body;
        }

        return substr($body, 0, $max).'…';
    }
}
