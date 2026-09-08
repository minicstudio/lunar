<?php

namespace Lunar\Klaviyo\Services;

use Illuminate\Support\Collection;
use Lunar\Exceptions\SilentException;
use Lunar\Facades\StorefrontSession;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Requests\BulkCreateCatalogItemsRequest;
use Lunar\Klaviyo\Requests\BulkCreateCatalogVariantsRequest;
use Lunar\Klaviyo\Requests\BulkDeleteCatalogItemsRequest;
use Lunar\Klaviyo\Requests\BulkUpdateCatalogItemsRequest;
use Lunar\Klaviyo\Requests\BulkUpdateCatalogVariantsRequest;
use Lunar\Klaviyo\Requests\CreateCatalogCategoryRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogItemRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogVariantRequest;
use Lunar\Klaviyo\Requests\GetBulkCreateCatalogItemsJobRequest;
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
    private const BULK_RESOURCE_LIMIT = 100;

    /**
     * @var array<string, array{external_id: string, id: string}>
     */
    protected array $ensuredCategories = [];

    public function __construct(protected KlaviyoService $klaviyo) {}

    /**
     * Upsert a single product via Klaviyo bulk catalog jobs (batch size 1).
     *
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function syncProduct(Product $product): array
    {
        $results = $this->syncProductsBulk(collect([$product]), synchronousUpdates: true);

        return $results[0] ?? [];
    }

    /**
     * Upsert many products via Klaviyo async bulk catalog jobs (≤100 resources per request).
     * Unavailable / unpublished products are deleted from the remote catalog.
     * Availability-check exceptions are rethrown (never coerced to delete).
     *
     * @param  iterable<int, Product>|Collection<int, Product>  $products
     * @param  bool  $synchronousUpdates  Patch existing resources immediately for lifecycle syncs
     * @return list<array<string, mixed>>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function syncProductsBulk(iterable $products, bool $synchronousUpdates = false): array
    {
        $this->ensureCatalogStorefrontContext();

        $products = $products instanceof Collection ? $products : collect($products);

        if ($products->isEmpty()) {
            return [];
        }

        $products->each(fn (Product $product) => $product->loadMissing(['variants', 'collections', 'brand', 'media']));

        $itemsToCreate = [];
        $itemsToUpdate = [];
        $variantsToCreate = [];
        $variantsToUpdate = [];
        /** @var list<array{product: Product, item_external_id: string, expected_variant_external_ids: list<string>}> $orphanCleanup */
        $orphanCleanup = [];
        $jobResults = [];
        /** @var list<array<string, mixed>> $itemCreateJobResponses */
        $itemCreateJobResponses = [];

        foreach ($products as $product) {
            $isPublished = $product->status === 'published';

            if ($isPublished) {
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

                continue;
            }

            $categoryIds = $this->ensureCategoriesForProduct($product);
            $itemExternalId = $this->resolveItemExternalId($product);
            $syncContext = $this->resolveCatalogItemSyncContext($product->id, $itemExternalId);
            $useUpdate = $syncContext['use_update'];
            $remoteVariantExternalIds = $syncContext['remote_variant_external_ids'];

            if ($useUpdate) {
                $itemsToUpdate[] = $this->buildCatalogItemUpdateResource($product, $categoryIds, $itemExternalId);
            } else {
                $itemsToCreate[] = $this->buildCatalogItemCreateResource($product, $categoryIds, $itemExternalId);
            }

            $expectedVariantExternalIds = [];

            foreach ($product->variants as $variant) {
                $variantExternalId = $this->resolveVariantExternalId($variant);
                $expectedVariantExternalIds[] = $variantExternalId;

                if ($useUpdate && isset($remoteVariantExternalIds[$variantExternalId])) {
                    $variantsToUpdate[] = $this->buildCatalogVariantUpdateResource(
                        $product,
                        $variant,
                        $itemExternalId,
                        $variantExternalId,
                    );
                } else {
                    $variantsToCreate[] = $this->buildCatalogVariantCreateResource(
                        $product,
                        $variant,
                        $itemExternalId,
                        $variantExternalId,
                    );
                }
            }

            $orphanCleanup[] = [
                'product' => $product,
                'item_external_id' => $itemExternalId,
                'expected_variant_external_ids' => $expectedVariantExternalIds,
            ];
        }

        foreach (array_chunk($itemsToCreate, self::BULK_RESOURCE_LIMIT) as $chunk) {
            $response = $this->submitCatalogItemBulkCreate($chunk);
            $itemCreateJobResponses[] = $response;
            $jobResults[] = $response;
        }

        foreach (array_chunk($itemsToUpdate, self::BULK_RESOURCE_LIMIT) as $chunk) {
            $jobResults[] = $this->submitCatalogItemBulkUpdate($chunk);
        }

        if ($itemCreateJobResponses !== []) {
            $this->waitForBulkCatalogItemCreateJobs($itemCreateJobResponses);
        }

        foreach (array_chunk($variantsToCreate, self::BULK_RESOURCE_LIMIT) as $chunk) {
            $jobResults[] = $this->submitCatalogVariantBulkCreate($chunk);
        }

        if ($synchronousUpdates) {
            foreach ($variantsToUpdate as $variant) {
                $jobResults[] = $this->updateCatalogVariant($variant);
            }
        } else {
            foreach (array_chunk($variantsToUpdate, self::BULK_RESOURCE_LIMIT) as $chunk) {
                $jobResults[] = $this->submitCatalogVariantBulkUpdate($chunk);
            }
        }

        foreach ($orphanCleanup as $entry) {
            $product = $entry['product'];
            $itemExternalId = $entry['item_external_id'];
            $orphansRemoved = $this->deleteOrphanCatalogVariants(
                $this->compoundId($itemExternalId),
                $entry['expected_variant_external_ids'],
            );

            CatalogExternalIdStore::remember($product->id, $itemExternalId);

            KlaviyoLogger::info('Catalog sync completed', [
                'product_id' => $product->id,
                'item_external_id' => $itemExternalId,
                'klaviyo_catalog_item_id' => $this->compoundId($itemExternalId),
                'variant_count' => $product->variants->count(),
                'orphan_variants_removed' => $orphansRemoved,
            ]);
        }

        return $jobResults;
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
     * Prevents stale remote variants when Lunar SKUs change but the item external_id stays the same.
     *
     * @param  list<string>  $expectedVariantExternalIds  Sanitized variant SKUs (legacy DB ids are removed as orphans)
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
        $remoteState = $this->fetchRemoteCatalogItemState($catalogItemCompoundId);

        if (! $remoteState['exists']) {
            KlaviyoLogger::info('Catalog item has no remote variants (item absent)', [
                'klaviyo_catalog_item_id' => $catalogItemCompoundId,
            ]);

            return [];
        }

        return $remoteState['variant_external_ids'];
    }

    /**
     * @return array{exists: bool, variant_external_ids: list<string>}
     *
     * @throws FailedKlaviyoSyncException
     */
    protected function fetchRemoteCatalogItemState(string $catalogItemCompoundId): array
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
                if ($response->status() === 404) {
                    return [
                        'exists' => false,
                        'variant_external_ids' => [],
                    ];
                }

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

        return [
            'exists' => true,
            'variant_external_ids' => array_values(array_unique($externalIds)),
        ];
    }

    /**
     * Delete a single catalog variant by its external_id (= sanitized variant SKU).
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

        if (isset($this->ensuredCategories[$externalId])) {
            return $this->ensuredCategories[$externalId];
        }

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

            return $this->ensuredCategories[$externalId] = [
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
     * Catalog variant external_id: prefer the variant SKU (sanitized).
     * Falls back to variant id when no SKU exists.
     */
    public function resolveVariantExternalId(ProductVariant $variant): string
    {
        $sku = trim((string) ($variant->sku ?? ''));

        if ($sku !== '') {
            return str_replace('/', '-', $sku);
        }

        KlaviyoLogger::warning('Catalog variant has no SKU — falling back to variant id', [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
        ]);

        return (string) $variant->id;
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

        return '';
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

        return '';
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
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function submitCatalogItemBulkCreate(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $payload = [
            'data' => [
                'type' => 'catalog-item-bulk-create-job',
                'attributes' => [
                    'items' => [
                        'data' => $items,
                    ],
                ],
            ],
        ];

        KlaviyoLogger::info('Catalog item bulk create job starting', [
            'item_count' => count($items),
        ]);

        $response = $this->klaviyo->getConnector()->send(new BulkCreateCatalogItemsRequest($payload));

        if (! $response->successful()) {
            throw new FailedKlaviyoSyncException(
                'Failed to create Klaviyo catalog item bulk create job: '.$response->body()
            );
        }

        $json = $response->json() ?? [];

        KlaviyoLogger::info('Catalog item bulk create job accepted', [
            'job_id' => $json['data']['id'] ?? null,
            'item_count' => count($items),
            'http_status' => $response->status(),
        ]);

        return $json;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function updateCatalogItem(array $item): array
    {
        $catalogItemId = $item['id'] ?? '';

        KlaviyoLogger::info('Catalog item update starting', [
            'klaviyo_catalog_item_id' => $catalogItemId,
        ]);

        $response = $this->klaviyo->getConnector()->send(new UpdateCatalogItemRequest($item));

        if (! $response->successful()) {
            throw new FailedKlaviyoSyncException(
                "Failed to update Klaviyo catalog item {$catalogItemId}: ".$response->body()
            );
        }

        $json = $response->json() ?? [];

        KlaviyoLogger::info('Catalog item update completed', [
            'klaviyo_catalog_item_id' => $catalogItemId,
            'http_status' => $response->status(),
        ]);

        return $json;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function submitCatalogItemBulkUpdate(array $items): array
    {
        if ($items === []) {
            return [];
        }

        if (count($items) === 1) {
            return $this->updateCatalogItem($items[0]);
        }

        $payload = [
            'data' => [
                'type' => 'catalog-item-bulk-update-job',
                'attributes' => [
                    'items' => [
                        'data' => $items,
                    ],
                ],
            ],
        ];

        KlaviyoLogger::info('Catalog item bulk update job starting', [
            'item_count' => count($items),
        ]);

        $response = $this->klaviyo->getConnector()->send(new BulkUpdateCatalogItemsRequest($payload));

        if (! $response->successful()) {
            throw new FailedKlaviyoSyncException(
                'Failed to create Klaviyo catalog item bulk update job: '.$response->body()
            );
        }

        $json = $response->json() ?? [];

        KlaviyoLogger::info('Catalog item bulk update job accepted', [
            'job_id' => $json['data']['id'] ?? null,
            'item_count' => count($items),
            'http_status' => $response->status(),
        ]);

        return $json;
    }

    /**
     * @param  list<array<string, mixed>>  $jobResponses
     *
     * @throws FailedKlaviyoSyncException
     */
    public function waitForBulkCatalogItemCreateJobs(array $jobResponses): void
    {
        foreach ($jobResponses as $jobResponse) {
            $jobId = $jobResponse['data']['id'] ?? null;

            if (! is_string($jobId) || $jobId === '') {
                continue;
            }

            $this->waitForBulkCatalogItemCreateJob($jobId);
        }
    }

    /**
     * @throws FailedKlaviyoSyncException
     */
    public function waitForBulkCatalogItemCreateJob(string $jobId, int $maxAttempts = 60, int $sleepSeconds = 1): void
    {
        KlaviyoLogger::info('Catalog item bulk create job waiting', [
            'job_id' => $jobId,
        ]);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->klaviyo->getConnector()->send(
                new GetBulkCreateCatalogItemsJobRequest($jobId, [
                    'fields[catalog-item-bulk-create-job]' => 'status,errors,failed_count,completed_count,total_count',
                ])
            );

            if (! $response->successful()) {
                throw new FailedKlaviyoSyncException(
                    "Failed to fetch Klaviyo catalog item bulk create job {$jobId}: {$response->body()}"
                );
            }

            $attributes = $response->json('data.attributes') ?? [];
            $status = $attributes['status'] ?? null;

            if ($status === 'complete') {
                $failedCount = (int) ($attributes['failed_count'] ?? 0);
                $errors = $attributes['errors'] ?? [];

                if ($failedCount > 0 || $errors !== []) {
                    KlaviyoLogger::error('Catalog item bulk create job completed with errors', [
                        'job_id' => $jobId,
                        'failed_count' => $failedCount,
                        'errors' => $errors,
                    ]);

                    throw new FailedKlaviyoSyncException(
                        "Klaviyo catalog item bulk create job {$jobId} completed with {$failedCount} failure(s)."
                    );
                }

                KlaviyoLogger::info('Catalog item bulk create job completed', [
                    'job_id' => $jobId,
                    'completed_count' => $attributes['completed_count'] ?? null,
                    'total_count' => $attributes['total_count'] ?? null,
                ]);

                return;
            }

            if ($status === 'cancelled') {
                throw new FailedKlaviyoSyncException(
                    "Klaviyo catalog item bulk create job {$jobId} was cancelled."
                );
            }

            sleep($sleepSeconds);
        }

        throw new FailedKlaviyoSyncException(
            "Timed out waiting for Klaviyo catalog item bulk create job {$jobId} to complete."
        );
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function submitCatalogVariantBulkCreate(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        $payload = [
            'data' => [
                'type' => 'catalog-variant-bulk-create-job',
                'attributes' => [
                    'variants' => [
                        'data' => $variants,
                    ],
                ],
            ],
        ];

        KlaviyoLogger::info('Catalog variant bulk create job starting', [
            'variant_count' => count($variants),
        ]);

        $response = $this->klaviyo->getConnector()->send(new BulkCreateCatalogVariantsRequest($payload));

        if (! $response->successful()) {
            throw new FailedKlaviyoSyncException(
                'Failed to create Klaviyo catalog variant bulk create job: '.$response->body()
            );
        }

        $json = $response->json() ?? [];

        KlaviyoLogger::info('Catalog variant bulk create job accepted', [
            'job_id' => $json['data']['id'] ?? null,
            'variant_count' => count($variants),
            'http_status' => $response->status(),
        ]);

        return $json;
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function submitCatalogVariantBulkUpdate(array $variants): array
    {
        if ($variants === []) {
            return [];
        }

        $payload = [
            'data' => [
                'type' => 'catalog-variant-bulk-update-job',
                'attributes' => [
                    'variants' => [
                        'data' => $variants,
                    ],
                ],
            ],
        ];

        KlaviyoLogger::info('Catalog variant bulk update job starting', [
            'variant_count' => count($variants),
        ]);

        $response = $this->klaviyo->getConnector()->send(new BulkUpdateCatalogVariantsRequest($payload));

        if (! $response->successful()) {
            throw new FailedKlaviyoSyncException(
                'Failed to create Klaviyo catalog variant bulk update job: '.$response->body()
            );
        }

        $json = $response->json() ?? [];

        KlaviyoLogger::info('Catalog variant bulk update job accepted', [
            'job_id' => $json['data']['id'] ?? null,
            'variant_count' => count($variants),
            'http_status' => $response->status(),
        ]);

        return $json;
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function updateCatalogVariant(array $variant): array
    {
        $catalogVariantId = $variant['id'] ?? '';

        KlaviyoLogger::info('Catalog variant update starting', [
            'klaviyo_catalog_variant_id' => $catalogVariantId,
        ]);

        $response = $this->klaviyo->getConnector()->send(new UpdateCatalogVariantRequest($variant));

        if (! $response->successful()) {
            throw new FailedKlaviyoSyncException(
                "Failed to update Klaviyo catalog variant {$catalogVariantId}: ".$response->body()
            );
        }

        $json = $response->json() ?? [];

        KlaviyoLogger::info('Catalog variant update completed', [
            'klaviyo_catalog_variant_id' => $catalogVariantId,
            'http_status' => $response->status(),
        ]);

        return $json;
    }

    /**
     * @param  list<string>  $categoryCompoundIds
     * @return array<string, mixed>
     */
    public function buildCatalogItemCreateResource(Product $product, array $categoryCompoundIds, string $itemExternalId): array
    {
        return [
            'type' => 'catalog-item',
            'attributes' => $this->buildCatalogItemAttributes($product, $itemExternalId),
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
        ];
    }

    /**
     * @param  list<string>  $categoryCompoundIds
     * @return array<string, mixed>
     */
    public function buildCatalogItemUpdateResource(Product $product, array $categoryCompoundIds, string $itemExternalId): array
    {
        $attributes = $this->buildCatalogItemAttributes($product, $itemExternalId);

        return [
            'type' => 'catalog-item',
            'id' => $this->compoundId($itemExternalId),
            'attributes' => collect($attributes)
                ->except(['external_id', 'integration_type', 'catalog_type'])
                ->all(),
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCatalogVariantCreateResource(
        Product $product,
        ProductVariant $variant,
        string $itemExternalId,
        string $variantExternalId,
    ): array {
        return [
            'type' => 'catalog-variant',
            'attributes' => $this->buildCatalogVariantAttributes($product, $variant, $variantExternalId),
            'relationships' => [
                'item' => [
                    'data' => [
                        'type' => 'catalog-item',
                        'id' => $this->compoundId($itemExternalId),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCatalogVariantUpdateResource(
        Product $product,
        ProductVariant $variant,
        string $itemExternalId,
        string $variantExternalId,
    ): array {
        return [
            'type' => 'catalog-variant',
            'id' => $this->compoundId($variantExternalId),
            'attributes' => collect($this->buildCatalogVariantAttributes($product, $variant, $variantExternalId))
                ->except(['external_id'])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCatalogItemAttributes(Product $product, string $itemExternalId): array
    {
        $title = $product->translateAttribute('name') ?? "Product {$product->id}";
        $description = strip_tags((string) ($product->translateAttribute('description') ?? $title));
        $url = $this->resolveProductUrl($product);
        $imageUrl = $product->getMedia('images', ['primary' => true])->first()?->getUrl('large');

        $attributes = [
            'external_id' => $itemExternalId,
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

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCatalogVariantAttributes(
        Product $product,
        ProductVariant $variant,
        string $variantExternalId,
    ): array {
        $title = $product->translateAttribute('name') ?? "Product {$product->id}";
        $description = strip_tags((string) ($product->translateAttribute('description') ?? $title));
        $url = $this->resolveProductUrl($product);
        $imageUrl = $product->getMedia('images', ['primary' => true])->first()?->getUrl('large');
        $defaultCurrency = Currency::getDefault();

        $price = $variant->getCurrentPricesIncTax()
            ->filter(fn ($price) => $price->currency->code === $defaultCurrency?->code)
            ->first();

        $attributes = [
            'external_id' => $variantExternalId,
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

        return $attributes;
    }

    /**
     * @return array{use_update: bool, remote_variant_external_ids: array<string, true>}
     */
    protected function resolveCatalogItemSyncContext(int $productId, string $itemExternalId): array
    {
        $catalogItemCompoundId = $this->compoundId($itemExternalId);
        $remoteState = $this->fetchRemoteCatalogItemState($catalogItemCompoundId);

        if (! $remoteState['exists']) {
            return [
                'use_update' => false,
                'remote_variant_external_ids' => [],
            ];
        }

        return [
            'use_update' => true,
            'remote_variant_external_ids' => array_fill_keys($remoteState['variant_external_ids'], true),
        ];
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
