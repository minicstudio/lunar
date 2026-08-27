<?php

namespace Lunar\Klaviyo\Services;

use Lunar\Exceptions\SilentException;
use Lunar\Facades\StorefrontSession;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Requests\BulkDeleteCatalogItemsRequest;
use Lunar\Klaviyo\Requests\CreateCatalogCategoryRequest;
use Lunar\Klaviyo\Requests\CreateCatalogItemRequest;
use Lunar\Klaviyo\Requests\CreateCatalogVariantRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogItemRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogVariantRequest;
use Lunar\Klaviyo\Requests\GetCatalogItemsRequest;
use Lunar\Klaviyo\Requests\GetCatalogItemVariantIdsRequest;
use Lunar\Klaviyo\Requests\UpdateCatalogItemRequest;
use Lunar\Klaviyo\Requests\UpdateCatalogVariantRequest;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Saloon\Http\Response;

class KlaviyoCatalogService
{
    public function __construct(protected KlaviyoService $klaviyo) {}

    /**
     * Upsert a product as a Klaviyo catalog item + variants.
     * Unavailable / unpublished products are deleted from the remote catalog.
     * Availability-check exceptions are rethrown (never coerced to delete).
     *
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function syncProduct(Product $product): array
    {
        $this->ensureCatalogStorefrontContext();

        $product->loadMissing(['variants', 'collections', 'brand', 'media']);

        $isPublished = $product->status === 'published';

        if ($isPublished) {
            // Exceptions must fail/retry the job — never treat as unavailable + delete.
            $isAvailable = $product->isAvailable();
        } else {
            $isAvailable = false;
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

            $this->deleteProductByExternalIds($this->captureExternalIdsForProduct($product));

            return [];
        }

        $categoryIds = $this->ensureCategoriesForProduct($product);
        $item = $this->upsertCatalogItem($product, $categoryIds);

        $variantResults = [];
        $expectedVariantExternalIds = [];

        foreach ($product->variants as $variant) {
            $expectedVariantExternalIds[] = (string) $variant->id;
            $variantResults[] = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'result' => $this->upsertCatalogVariant($product, $variant) ? 'ok' : 'failed',
            ];
        }

        $itemExternalId = $this->resolveItemExternalId($product);
        $orphansRemoved = $this->deleteOrphanCatalogVariants(
            $this->compoundId($itemExternalId),
            $expectedVariantExternalIds,
        );

        CatalogExternalIdStore::remember($product->id, $itemExternalId);

        $catalogItemId = $item['data']['id'] ?? $this->compoundId($itemExternalId);

        KlaviyoLogger::info('Catalog sync completed', [
            'product_id' => $product->id,
            'item_external_id' => $itemExternalId,
            'klaviyo_catalog_item_id' => $catalogItemId,
            'category_ids' => $categoryIds,
            'variant_count' => $product->variants->count(),
            'variants' => $variantResults,
            'orphan_variants_removed' => $orphansRemoved,
        ]);

        return $item;
    }

    /**
     * @deprecated Use deleteProductByExternalIds with captured ids.
     *
     * @throws FailedKlaviyoSyncException
     */
    public function deleteProduct(Product $product): bool
    {
        return $this->deleteProductByExternalIds($this->captureExternalIdsForProduct($product));
    }

    /**
     * Delete catalog items by captured external_id strings (SKU-based + legacy product-id).
     * Never re-derives SKU from deleted variants.
     *
     * @param  list<string>  $externalIds
     *
     * @throws FailedKlaviyoSyncException
     */
    public function deleteProductByExternalIds(array $externalIds): bool
    {
        $externalIds = array_values(array_unique(array_filter($externalIds, fn ($id) => is_string($id) && $id !== '')));

        if ($externalIds === []) {
            KlaviyoLogger::warning('Catalog delete skipped — no external ids captured');

            return true;
        }

        $allOk = true;

        foreach ($externalIds as $externalId) {
            $catalogItemId = $this->compoundId($externalId);

            KlaviyoLogger::info('Catalog delete started', [
                'item_external_id' => $externalId,
                'klaviyo_catalog_item_id' => $catalogItemId,
            ]);

            $response = $this->klaviyo->getConnector()->send(new DeleteCatalogItemRequest($catalogItemId));

            if ($response->successful() || $response->status() === 404) {
                KlaviyoLogger::info('Catalog delete finished', [
                    'item_external_id' => $externalId,
                    'klaviyo_catalog_item_id' => $catalogItemId,
                    'http_status' => $response->status(),
                    'already_absent' => $response->status() === 404,
                ]);

                continue;
            }

            $allOk = false;

            KlaviyoLogger::error('Catalog delete API failed', [
                'item_external_id' => $externalId,
                'klaviyo_catalog_item_id' => $catalogItemId,
                'http_status' => $response->status(),
                'body' => $this->truncateBody($response->body()),
            ]);

            throw new FailedKlaviyoSyncException(
                "Failed to delete Klaviyo catalog item {$externalId}: {$response->body()}"
            );
        }

        return $allOk;
    }

    /**
     * List every remote catalog item id, then spawn bulk delete jobs (≤100 items each).
     * Deleting an item also removes its variants in Klaviyo.
     *
     * @return array{deleted: int, jobs: int}
     *
     * @throws FailedKlaviyoSyncException
     */
    public function deleteAllCatalogItems(int $pageSize = 100): array
    {
        $pageSize = max(1, min(100, $pageSize));
        $itemIds = $this->listAllCatalogItemIds($pageSize);

        if ($itemIds === []) {
            KlaviyoLogger::info('Catalog wipe skipped — no remote catalog items found');

            return ['deleted' => 0, 'jobs' => 0];
        }

        $jobs = 0;

        foreach (array_chunk($itemIds, 100) as $chunk) {
            $payload = [
                'data' => [
                    'type' => 'catalog-item-bulk-delete-job',
                    'attributes' => [
                        'items' => [
                            'data' => array_map(
                                fn (string $id) => [
                                    'type' => 'catalog-item',
                                    'id' => $id,
                                ],
                                $chunk
                            ),
                        ],
                    ],
                ],
            ];

            KlaviyoLogger::info('Catalog bulk delete job starting', [
                'item_count' => count($chunk),
            ]);

            $response = $this->klaviyo->getConnector()->send(new BulkDeleteCatalogItemsRequest($payload));

            if (! $response->successful()) {
                KlaviyoLogger::error('Catalog bulk delete job failed', [
                    'http_status' => $response->status(),
                    'body' => $this->truncateBody($response->body()),
                ]);

                throw new FailedKlaviyoSyncException(
                    'Failed to create Klaviyo catalog item bulk delete job: '.$response->body()
                );
            }

            $jobs++;

            KlaviyoLogger::info('Catalog bulk delete job accepted', [
                'job_id' => $response->json('data.id'),
                'item_count' => count($chunk),
                'http_status' => $response->status(),
            ]);
        }

        return [
            'deleted' => count($itemIds),
            'jobs' => $jobs,
        ];
    }

    /**
     * @return list<string> Compound catalog item ids (`$custom:::$default:::{external_id}`)
     *
     * @throws FailedKlaviyoSyncException
     */
    public function listAllCatalogItemIds(int $pageSize = 100): array
    {
        $pageSize = max(1, min(100, $pageSize));
        $itemIds = [];
        $cursor = null;

        do {
            $query = [
                'page[size]' => $pageSize,
            ];

            if ($cursor !== null) {
                $query['page[cursor]'] = $cursor;
            }

            $response = $this->klaviyo->getConnector()->send(new GetCatalogItemsRequest($query));

            if (! $response->successful()) {
                KlaviyoLogger::error('Catalog list items API failed', [
                    'http_status' => $response->status(),
                    'body' => $this->truncateBody($response->body()),
                ]);

                throw new FailedKlaviyoSyncException(
                    'Failed to list Klaviyo catalog items: '.$response->body()
                );
            }

            $json = $response->json() ?? [];

            foreach ($json['data'] ?? [] as $item) {
                $id = $item['id'] ?? null;

                if (is_string($id) && $id !== '') {
                    $itemIds[] = $id;
                }
            }

            $cursor = $this->extractPageCursor($json['links']['next'] ?? null);
        } while ($cursor !== null);

        return array_values(array_unique($itemIds));
    }

    /**
     * Delete remote catalog variants attached to an item that are not in the expected set.
     * Prevents duplicate SKUs when Lunar variant ids change but the item external_id (SKU) stays the same.
     *
     * @param  list<string>  $expectedVariantExternalIds  Lunar variant ids as strings
     * @return list<string> Deleted variant external ids
     *
     * @throws FailedKlaviyoSyncException
     */
    public function deleteOrphanCatalogVariants(string $catalogItemCompoundId, array $expectedVariantExternalIds): array
    {
        $expected = array_fill_keys(
            array_map('strval', $expectedVariantExternalIds),
            true
        );

        $remoteExternalIds = $this->listCatalogItemVariantExternalIds($catalogItemCompoundId);
        $removed = [];

        foreach ($remoteExternalIds as $remoteExternalId) {
            if (isset($expected[$remoteExternalId])) {
                continue;
            }

            $this->deleteCatalogVariant($remoteExternalId);
            $removed[] = $remoteExternalId;
        }

        if ($removed !== []) {
            KlaviyoLogger::warning('Catalog orphan variants removed', [
                'klaviyo_catalog_item_id' => $catalogItemCompoundId,
                'expected_variant_external_ids' => array_keys($expected),
                'removed_variant_external_ids' => $removed,
            ]);
        }

        return $removed;
    }

    /**
     * @return list<string> Variant external_ids (not compound ids)
     *
     * @throws FailedKlaviyoSyncException
     */
    public function listCatalogItemVariantExternalIds(string $catalogItemCompoundId): array
    {
        $externalIds = [];
        $cursor = null;

        do {
            $query = [
                'page[size]' => 100,
            ];

            if ($cursor !== null) {
                $query['page[cursor]'] = $cursor;
            }

            $response = $this->klaviyo->getConnector()->send(
                new GetCatalogItemVariantIdsRequest($catalogItemCompoundId, $query)
            );

            if (! $response->successful()) {
                KlaviyoLogger::error('Catalog list item variants API failed', [
                    'klaviyo_catalog_item_id' => $catalogItemCompoundId,
                    'http_status' => $response->status(),
                    'body' => $this->truncateBody($response->body()),
                ]);

                throw new FailedKlaviyoSyncException(
                    "Failed to list Klaviyo catalog variants for item {$catalogItemCompoundId}: {$response->body()}"
                );
            }

            $json = $response->json() ?? [];

            foreach ($json['data'] ?? [] as $variant) {
                $compoundId = $variant['id'] ?? null;

                if (! is_string($compoundId) || $compoundId === '') {
                    continue;
                }

                $externalIds[] = $this->externalIdFromCompoundId($compoundId);
            }

            $cursor = $this->extractPageCursor($json['links']['next'] ?? null);
        } while ($cursor !== null);

        return array_values(array_unique($externalIds));
    }

    /**
     * Delete a single catalog variant by its external_id (= Lunar variant id).
     *
     * @throws FailedKlaviyoSyncException
     */
    public function deleteCatalogVariant(string $variantExternalId): bool
    {
        if ($variantExternalId === '') {
            return true;
        }

        $catalogVariantId = $this->compoundId($variantExternalId);

        KlaviyoLogger::info('Catalog variant delete started', [
            'variant_external_id' => $variantExternalId,
            'klaviyo_catalog_variant_id' => $catalogVariantId,
        ]);

        $response = $this->klaviyo->getConnector()->send(new DeleteCatalogVariantRequest($catalogVariantId));

        if ($response->successful() || $response->status() === 404) {
            KlaviyoLogger::info('Catalog variant delete finished', [
                'variant_external_id' => $variantExternalId,
                'http_status' => $response->status(),
                'already_absent' => $response->status() === 404,
            ]);

            return true;
        }

        KlaviyoLogger::error('Catalog variant delete API failed', [
            'variant_external_id' => $variantExternalId,
            'http_status' => $response->status(),
            'body' => $this->truncateBody($response->body()),
        ]);

        throw new FailedKlaviyoSyncException(
            "Failed to delete Klaviyo catalog variant {$variantExternalId}: {$response->body()}"
        );
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

    /**
     * Catalog item external_id: prefer the first non-empty variant SKU.
     * Falls back to product id when no SKU exists. Must not contain `/`.
     */
    public function resolveItemExternalId(Product $product): string
    {
        $product->unsetRelation('variants');
        $product->load(['variants' => fn ($query) => $query->withTrashed()]);

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

    /**
     * Rewrite storefront event product/variant identifiers to SKU-based values.
     *
     * Neutral events emit Lunar DB ids in `product_id` / `product_id_{n}` / `variant_id`.
     * Klaviyo catalog items are keyed by SKU, so behavioral events must match.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function mapEventProductIdentifiers(array $properties): array
    {
        if (array_key_exists('product_id', $properties)) {
            $properties['product_id'] = $this->resolveEventProductIdentifier($properties['product_id']);
        }

        foreach (array_keys($properties) as $key) {
            if (! is_string($key) || ! preg_match('/^product_id_\d+$/', $key)) {
                continue;
            }

            $properties[$key] = $this->resolveEventProductIdentifier($properties[$key]);
        }

        if (array_key_exists('variant_id', $properties)) {
            $properties['variant_id'] = $this->resolveEventVariantIdentifier(
                $properties['variant_id'],
                $properties['sku'] ?? null,
            );
        }

        foreach (array_keys($properties) as $key) {
            if (! is_string($key) || ! preg_match('/^variant_id_(\d+)$/', $key, $matches)) {
                continue;
            }

            $properties[$key] = $this->resolveEventVariantIdentifier(
                $properties[$key],
                $properties['sku_'.$matches[1]] ?? null,
            );
        }

        return $properties;
    }

    /**
     * Resolve a storefront product_id value to the catalog item external_id.
     */
    public function resolveEventProductIdentifier(mixed $productId): string
    {
        if ($productId === null || $productId === '') {
            return '';
        }

        $productIdString = trim((string) $productId);

        if ($productIdString === '') {
            return '';
        }

        // Already a non-numeric identifier (SKU) — sanitize like catalog.
        if (! ctype_digit($productIdString)) {
            return str_replace('/', '-', $productIdString);
        }

        $id = (int) $productIdString;

        $stored = CatalogExternalIdStore::get($id);

        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        $product = Product::query()->find($id);

        if ($product) {
            return $this->resolveItemExternalId($product);
        }

        return $productIdString;
    }

    /**
     * Resolve a storefront variant_id value to the variant SKU.
     */
    public function resolveEventVariantIdentifier(mixed $variantId, mixed $skuHint = null): string
    {
        $skuHint = trim((string) ($skuHint ?? ''));

        if ($skuHint !== '') {
            return str_replace('/', '-', $skuHint);
        }

        if ($variantId === null || $variantId === '') {
            return '';
        }

        $variantIdString = trim((string) $variantId);

        if ($variantIdString === '') {
            return '';
        }

        // Already a non-numeric identifier (SKU) — sanitize like catalog.
        if (! ctype_digit($variantIdString)) {
            return str_replace('/', '-', $variantIdString);
        }

        $variant = ProductVariant::query()->withTrashed()->find((int) $variantIdString);

        if ($variant) {
            $sku = trim((string) ($variant->sku ?? ''));

            if ($sku !== '') {
                return str_replace('/', '-', $sku);
            }
        }

        return $variantIdString;
    }

    /**
     * Capture item + legacy external ids while variants still exist (or from store).
     *
     * @return list<string>
     */
    public function captureExternalIdsForProduct(Product $product): array
    {
        $ids = [];

        $stored = CatalogExternalIdStore::get($product->id);

        if ($stored !== null) {
            $ids[] = $stored;
        }

        try {
            $product->loadMissing(['variants' => fn ($q) => $q->withTrashed()]);
            $ids[] = $this->resolveItemExternalId($product);
        } catch (\Throwable $e) {
            KlaviyoLogger::warning('Could not resolve item external id from product variants', [
                'product_id' => $product->id,
                'exception' => $e->getMessage(),
            ]);
        }

        $ids[] = (string) $product->id;

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Capture external ids for a product id without requiring a restored Eloquent model.
     *
     * @return list<string>
     */
    public function captureExternalIdsForProductId(int $productId, ?string $itemExternalId = null, array $additionalExternalIds = []): array
    {
        $ids = [];

        if (is_string($itemExternalId) && $itemExternalId !== '') {
            $ids[] = $itemExternalId;
        }

        $stored = CatalogExternalIdStore::get($productId);

        if ($stored !== null) {
            $ids[] = $stored;
        }

        foreach ($additionalExternalIds as $additionalId) {
            if (is_string($additionalId) && $additionalId !== '') {
                $ids[] = $additionalId;
            }
        }

        $ids[] = (string) $productId;

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function upsertCatalogVariant(Product $product, ProductVariant $variant): array
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

    /**
     * Queue workers have no storefront session — pin default channel + customer group.
     */
    protected function ensureCatalogStorefrontContext(): void
    {
        $channel = Channel::getDefault();

        if ($channel) {
            StorefrontSession::setChannel($channel);
        }

        $customerGroup = CustomerGroup::getDefault();

        if ($customerGroup) {
            StorefrontSession::setCustomerGroups(collect([$customerGroup]));
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

    protected function compoundId(string $externalId): string
    {
        return '$custom:::$default:::'.$externalId;
    }

    /**
     * Extract external_id from a compound catalog id (`$custom:::$default:::{external_id}`).
     */
    protected function externalIdFromCompoundId(string $compoundId): string
    {
        $parts = explode(':::', $compoundId);

        return (string) ($parts[array_key_last($parts)] ?? $compoundId);
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

    /**
     * Extract `page[cursor]` from a Klaviyo JSON:API `links.next` URL.
     */
    protected function extractPageCursor(mixed $nextLink): ?string
    {
        if (! is_string($nextLink) || $nextLink === '') {
            return null;
        }

        $query = parse_url($nextLink, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);

        $cursor = $params['page']['cursor'] ?? $params['page[cursor]'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }
}
