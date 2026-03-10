<?php

namespace Lunar\ERP\Providers\Magister;

use Illuminate\Support\Facades\Log;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Contracts\ErpDataImporterInterface;
use Lunar\ERP\Exceptions\ErpSyncException;
use Lunar\ERP\Models\ErpSyncLog;
use Lunar\ERP\Models\ErpSyncTemp;
use Lunar\ERP\Providers\Magister\Jobs\CreateProductsAndVariantsJob;
use Lunar\ERP\Support\OrderStatusUpdater;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

class MagisterErpImporter implements ErpDataImporterInterface
{
    /**
     * The ERP API client instance.
     */
    protected ErpApiClientInterface $client;

    /**
     * Create a new Magister ERP importer instance.
     */
    public function __construct(ErpApiClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Sync products from Magister ERP system.
     */
    public function syncProducts(?callable $progressCallback = null): array
    {
        $syncLog = ErpSyncLog::create([
            'provider' => 'magister',
            'sync_type' => 'products',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $totalProcessed = 0;
            $cycleComplete = false;

            // Implement the Magister API cycle
            while (! $cycleComplete) {
                $response = $this->client->getProductList();

                // Check if response is null/empty (end of cycle)
                if (empty($response) || empty($response['result']) || empty($response['result'][0]['DATASET'])) {
                    $cycleComplete = true;
                    break;
                }

                $articles = $response['result'][0]['DATASET'] ?? [];

                // If no articles in this batch, cycle is complete
                if (empty($articles)) {
                    $cycleComplete = true;
                    break;
                }

                $recVersion = $response['result'][0]['DATASET'][0]['RECVERSION'] ?? null;

                // Store articles temporarily for further processing
                $totalProcessed += $this->storeTemporarily($articles, $progressCallback);
                $totalArticles = count($articles);

                if ($progressCallback) {
                    $progressCallback($totalArticles, $totalArticles, "Storing magister data temporarily: {$totalArticles}/{$totalArticles}");
                }

                // After processing and saving data, confirm receiving
                // typeOf=101 for articles/products
                $confirmResponse = $this->client->confirmReceivingData(101, $recVersion);

                // Log the confirmation for debugging
                Log::info('Magister ERP: Confirmed receiving data batch', [
                    'recVersion' => $recVersion,
                    'processed_items' => count($articles),
                    'confirmation_response' => $confirmResponse,
                ]);

                // Continue to next batch
            }

            $syncLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'items_processed' => $totalProcessed,
                'sync_data' => ['cycle_completed' => true],
            ]);

            // Sync stock data to temp table before creating products
            $this->syncStockToTempTable($progressCallback);

            $this->createProductsAndVariantsFromTemp($progressCallback);

            return [
                'success' => true,
                'products_processed' => $totalProcessed,
                'sync_log_id' => $syncLog->id,
            ];

        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw new ErpSyncException('Product sync failed: '.$e->getMessage());
        }
    }

    /**
     * Sync order statuses from ERP system.
     */
    public function syncOrderStatuses(): array
    {
        $syncLog = ErpSyncLog::create([
            'provider' => 'magister',
            'sync_type' => 'order_statuses',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $totalProcessed = 0;

            $response = $this->client->getModifiedOrders();

            $recVersion = null;

            if (! empty($response['result'][0]['DATASET']) && isset($response['result'][0]['DATASET'][0]['RECVERSION'])) {
                $recVersion = $response['result'][0]['DATASET'][0]['RECVERSION'];
            }

            $orders = $response['result'][0]['DATASET'] ?? [];

            $statusUpdater = new OrderStatusUpdater;

            foreach ($orders as $order) {
                $orderEntry = Order::where('reference', $order['ORDER_NUMBER'])->first();

                // if the order is not found in our system, we skip it and Log a warning
                if (! $orderEntry) {
                    Log::warning('Magister ERP: Order not found in system, skipping', [
                        'reference' => $order['ORDER_NUMBER'],
                    ]);

                    continue;
                }

                $externalStatus = $order['STATUS'];
                $externalSubStatus = $order['STATUS_SUBTYPE'];
                $internalStatus = (new OrderStatusMapper)($externalStatus, $externalSubStatus);

                // if the new status is different from the current status, we update the order
                if ($orderEntry->status !== $internalStatus) {

                    // save the AWB number if provided
                    if (! empty($order['SHIPPING_DOC'])) {
                        $orderEntry->meta['awb'] = $order['SHIPPING_DOC'];
                    }

                    // Get mailers configured for this status in config/lunar.php
                    $mailers = config("lunar.orders.statuses.{$internalStatus}.mailers", []);

                    // Prepare the same $data array Filament uses
                    $data = [
                        'status' => $internalStatus,
                        'send_notifications' => true,
                        'mailers' => $mailers,
                        'email_addresses' => [
                            $orderEntry->billingAddress?->contact_email,
                            $orderEntry->shippingAddress?->contact_email,
                        ],
                        'additional_email' => null,
                        'additional_content' => '',
                    ];

                    $orderEntry->status = $internalStatus;
                    $orderEntry->save();

                    $statusUpdater->handle($orderEntry, $data);
                }
                $totalProcessed++;
            }

            // After processing and saving data, confirm receiving
            // typeOf=2 for orders
            if ($recVersion !== null) {
                $confirmResponse = $this->client->confirmReceivingData(2, $recVersion);
            }

            // Log the confirmation for debugging
            Log::info('Magister ERP: Confirmed receiving data batch', [
                'recVersion' => $recVersion,
                'processed_items' => $totalProcessed,
                'confirmation_response' => $confirmResponse ?? null,
            ]);

            $syncLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'items_processed' => $totalProcessed,
            ]);

            return [
                'success' => true,
                'orders_processed' => $totalProcessed,
                'sync_log_id' => $syncLog->id,
            ];

        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw new ErpSyncException('Order status sync failed: '.$e->getMessage());
        }
    }

    /**
     * Sync stock levels from Magister ERP system.
     */
    public function syncStock(?callable $progressCallback = null): array
    {
        $syncLog = ErpSyncLog::create([
            'provider' => 'magister',
            'sync_type' => 'stock',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $cycleComplete = false;
            $totalProcessed = 0;

            // Implement the Magister API cycle
            while (! $cycleComplete) {
                $response = $this->client->getStock();

                // Check if response is null/empty (end of cycle)
                if (empty($response) || empty($response['result']) || empty($response['result'][0]['DATASET'])) {
                    $cycleComplete = true;
                    break;
                }

                $stockData = $response['result'][0]['DATASET'] ?? [];

                // If empty response, cycle is complete
                if (empty($stockData)) {
                    $cycleComplete = true;
                    break;
                }

                $recVersion = $response['result'][0]['DATASET'][0]['RECVERSION'] ?? null;
                $totalStockData = count($stockData);

                foreach ($stockData as $index => $stockItem) {
                    $this->updateProductStock($stockItem);
                    $totalProcessed++;

                    if ($progressCallback && ($totalProcessed % 5 === 0 || $index === $totalStockData - 1)) {
                        $progressCallback($totalProcessed, $totalStockData, "Processing stock data: {$totalProcessed}/{$totalStockData}");
                    }
                }

                // After processing and saving data, confirm receiving
                // typeOf=1 for stock
                $confirmResponse = $this->client->confirmReceivingData(1, $recVersion);

                // Log the confirmation for debugging
                Log::info('Magister ERP: Confirmed receiving data batch', [
                    'recVersion' => $recVersion,
                    'processed_items' => $totalProcessed,
                    'confirmation_response' => $confirmResponse,
                ]);

                // Continue to next batch
            }

            $syncLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'items_processed' => $totalProcessed,
                'sync_data' => ['cycle_completed' => true],
            ]);

            return [
                'success' => true,
                'stock_items_processed' => $totalProcessed,
                'sync_log_id' => $syncLog->id,
            ];

        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw new ErpSyncException('Stock sync failed: '.$e->getMessage());
        }
    }

    /**
     * Store articles temporarily in the ErpSyncTemp table.
     *
     * @return int Total number of articles processed
     */
    protected function storeTemporarily(array $articles, ?callable $progressCallback = null): int
    {
        $totalProcessed = 0;
        $totalArticles = count($articles);

        foreach ($articles as $index => $articleData) {
            // Show progress every 10 items or at the end
            if ($progressCallback && ($totalProcessed % 10 === 0 || $index === $totalArticles - 1)) {
                $progressCallback($totalProcessed, $totalArticles, "Storing magister data temporarily: {$totalProcessed}/{$totalArticles}");
            }

            if (! isset($articleData['SALECODE']) || empty($articleData['SALECODE'])) {
                // Skip articles without SALECODE (like discount or any other non-product items)
                continue;
            }

            ErpSyncTemp::updateOrCreate(
                [
                    'erp_id' => $articleData['IDSMARTCASH'],
                    'sku' => $articleData['SALECODE'],
                ],
                [
                    'name' => $articleData['NAME'],
                    'price' => $articleData['PRICE'] * 100,
                    'stock' => 0, // Initialize with 0, will be updated during stock sync
                    'category_1' => $articleData['CATEG_1'],
                    'category_2' => $articleData['CATEG_2'],
                    'provider_data' => $this->getProviderSpecificData($articleData),
                    'attributes' => $articleData['ITEM_ATTRIBUTES'] ?? [],
                ]
            );
            $totalProcessed++;
        }

        return $totalProcessed;
    }

    /**
     * Update product stock from Magister data.
     */
    protected function updateProductStock(array $stockItem): void
    {
        // skip stock coming from NRSHOP 1
        if (isset($stockItem['NRSHOP']) && $stockItem['NRSHOP'] === 1) {
            return;
        }

        $idSmartcash = $stockItem['IDSMARTCASH'] ?? null;
        $stock = (int) ($stockItem['STOCK'] ?? 0);

        $variant = ProductVariant::where('erp_id', $idSmartcash)->first();

        if (! $variant) {
            Log::warning('Magister ERP: Stock update skipped, variant not found for SMARTCASH ID', [
                'IDSMARTCASH' => $idSmartcash,
                'stock_item' => $stockItem,
            ]);

            return;
        }

        // Only update if stock actually changed - CRITICAL OPTIMIZATION
        if ($variant->stock === $stock) {
            return; // No change, skip save and avoid triggering events/jobs
        }

        $variant->stock = $stock;
        $variant->save();
    }

    /**
     * Create products and variants from temporary data.
     */
    protected function createProductsAndVariantsFromTemp(?callable $progressCallback = null): void
    {
        // Get articles with stock > 0
        $articlesWithStock = ErpSyncTemp::where('stock', '>', 0)->get();

        // Find generic articles that have variants with stock > 0
        $variantsWithStock = ErpSyncTemp::where('provider_data->article_kind', 2) // variants
            ->where('stock', '>', 0)
            ->get();

        $genericArticleIds = $variantsWithStock
            ->pluck('provider_data.generic_article_id')
            ->filter() // Remove nulls
            ->unique();

        $genericArticles = ErpSyncTemp::where('provider_data->article_kind', 1) // generic articles
            ->whereIn('erp_id', $genericArticleIds)
            ->get();

        // Combine articles: those with stock + generic articles with stocked variants
        $allArticlesToProcess = $articlesWithStock->concat($genericArticles)->unique('id');

        $totalArticles = $allArticlesToProcess->count();

        if ($progressCallback && $totalArticles > 0) {
            $progressCallback(0, $totalArticles, "Processing articles with stock > 0: {$totalArticles} articles found");
        }

        // order articles by ARTICLE_KIND so that standard (0) articles come first, then generics (1), and finally variants (2)
        $allArticlesToProcess = $allArticlesToProcess->sortBy(function ($article) {
            return $article->provider_data['article_kind'] ?? 0;
        });

        $processed = 0;
        foreach ($allArticlesToProcess as $article) {
            $processed++;

            // Show progress every 5 items or at the end
            if ($progressCallback && ($processed % 5 === 0 || $processed === $totalArticles)) {
                $progressCallback($processed, $totalArticles, "Creating products: {$processed}/{$totalArticles}");
            }

            if (! ErpSyncTemp::where('id', $article->id)->exists()) {
                continue;
            }

            // For variant articles (article_kind = 2), skip them if they belong to a generic article
            // They will be processed when the generic article is processed
            if (isset($article->provider_data['article_kind']) &&
                $article->provider_data['article_kind'] === 2 &&
                isset($article->provider_data['generic_article_id']) &&
                $genericArticleIds->contains($article->provider_data['generic_article_id'])) {
                continue;
            }

            // Find related variants - only include those with stock > 0 for generic products
            if (isset($article->provider_data['article_kind']) && $article->provider_data['article_kind'] === 1) {
                // For generic articles, only include variants that have stock > 0
                // Convert erp_id to int for comparison since JSON stores numbers as integers
                $erpId = is_numeric($article->erp_id) ? (int) $article->erp_id : $article->erp_id;
                $relatedSmartcashVariants = ErpSyncTemp::where('provider_data->generic_article_id', $erpId)
                    ->where('stock', '>', 0)
                    ->get();
            } else {
                // For standard articles, get related variants normally
                $erpId = is_numeric($article->erp_id) ? (int) $article->erp_id : $article->erp_id;
                $relatedSmartcashVariants = ErpSyncTemp::where('provider_data->generic_article_id', $erpId)->get();
            }

            // articles need to be handled together with their variants
            CreateProductsAndVariantsJob::dispatch($article, $relatedSmartcashVariants);
        }
    }

    /**
     * Sync stock data to temporary table.
     */
    protected function syncStockToTempTable(?callable $progressCallback = null): void
    {
        $totalProcessed = 0;

        if ($progressCallback) {
            $progressCallback(0, 1, 'Starting stock sync to temp table...');
        }

        // Loop through the temp table and get the stock for each article
        $tempArticles = ErpSyncTemp::all();

        foreach ($tempArticles as $index => $tempArticle) {
            // skip the articles that already have stock > 0
            if ($tempArticle->stock > 0) {
                $totalProcessed++;

                continue;
            }

            try {
                $response = $this->client->getArticleStockByShop($tempArticle->erp_id);
            } catch (\Exception $e) {
                Log::error('Magister ERP: Exception while getting stock for article', [
                    'erp_id' => $tempArticle->erp_id,
                    'exception_message' => $e->getMessage(),
                ]);

                continue;
            }

            // if there is no result or it is empty, log an error and continue
            if (empty($response) || empty($response['result'])) {
                Log::error('Magister ERP: Failed to get stock for article', [
                    'erp_id' => $tempArticle->erp_id,
                    'response' => $response,
                ]);

                continue;
            }

            // if there is result but the DATASET is empty, delete the entry from temp table
            if (empty($response['result'][0]['DATASET'])) {
                Log::info('Magister ERP: No stock data found for article, deleting from temp table', [
                    'erp_id' => $tempArticle->erp_id,
                    'response' => $response,
                ]);
                $tempArticle->delete();

                continue;
            }

            $dataset = $response['result'][0]['DATASET'];

            $webshopStockData = collect($dataset)->firstWhere('NRSHOP', 98); // only get stock for webshop NRSHOP 98

            $stock = $webshopStockData['STOCK'] ?? 0;

            // if the stock is 0 then delete the entry from temp table
            if ($stock <= 0) {
                $tempArticle->delete();

                continue;
            }

            $tempArticle->stock = $stock;
            $tempArticle->save();

            $totalProcessed++;

            if ($progressCallback && ($totalProcessed % 10 === 0 || $index === $tempArticles->count() - 1)) {
                $progressCallback($totalProcessed, $tempArticles->count(), "Stock sync to temp table: {$totalProcessed}/{$tempArticles->count()}");
            }
        }

        if ($progressCallback) {
            $progressCallback($totalProcessed, $totalProcessed, 'Stock sync to temp table completed.');
        }
    }

    /**
     * Update stock in temporary table based on Magister stock data.
     */
    protected function updateTempTableStock(array $stockItem): void
    {
        // skip stock coming from NRSHOP 1
        if (isset($stockItem['NRSHOP']) && $stockItem['NRSHOP'] === 1) {
            return;
        }

        $idSmartcash = $stockItem['IDSMARTCASH'] ?? null;
        $stock = $stockItem['STOCK'] ?? 0;

        if (! $idSmartcash) {
            return;
        }

        // Update the stock in the temp table
        ErpSyncTemp::where('erp_id', $idSmartcash)->update(['stock' => $stock]);
    }

    /**
     * Get the provider-specific data structure for storing in the provider_data JSON column.
     *
     * @param  array  $rawData  The raw data from the ERP system
     * @return array The structured provider-specific data
     */
    protected function getProviderSpecificData(array $rawData): array
    {
        return [
            'article_kind' => $rawData['ARTICLE_KIND'] ?? null,
            'generic_article_id' => $rawData['IDSMARTCASH_GENERIC_ARTICLE'] ?? null,
            'recversion' => $rawData['RECVERSION'] ?? null,
        ];
    }
}
