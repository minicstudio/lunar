<?php

namespace Lunar\Klaviyo\Commands;

use Illuminate\Console\Command;
use Lunar\Klaviyo\Services\KlaviyoOrderService;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Models\Order;

class SyncAllOrdersToKlaviyoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'klaviyo:sync-all-orders
                            {--chunk=50 : Number of orders to process at a time}';

    /**
     * @var string
     */
    protected $description = 'Sync all placed orders from the database to Klaviyo as Placed Order / Ordered Product events';

    public function handle(KlaviyoOrderService $orderService): int
    {
        if (! KlaviyoAvailability::enabled()) {
            $this->error('Klaviyo integration is not enabled. Set KLAVIYO_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        if (! KlaviyoAvailability::syncOrders()) {
            $this->error('Order sync is not enabled. Set KLAVIYO_SYNC_ORDERS=true in your .env file.');

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));

        $query = Order::query()->whereNotNull('placed_at');
        $totalOrders = (clone $query)->count();

        if ($totalOrders === 0) {
            $this->info('No orders found to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalOrders} order(s) to sync.");
        $this->warn('Note: This will send Placed Order / Ordered Product events to Klaviyo.');

        if (! $this->confirm('Do you want to proceed with syncing all orders to Klaviyo?', true)) {
            $this->info('Sync cancelled.');

            return self::SUCCESS;
        }

        $this->info('Starting order sync...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalOrders);
        $progressBar->start();

        $successCount = 0;
        $skippedCount = 0;
        $failureCount = 0;
        /** @var list<array{order_id: int, reference: string, error: string}> $errors */
        $errors = [];

        $query
            ->with(['user', 'billingAddress', 'currency', 'productLines.purchasable.product.variants'])
            ->orderBy('id')
            ->chunk($chunkSize, function ($orders) use ($orderService, &$successCount, &$skippedCount, &$failureCount, &$errors, $progressBar): void {
                foreach ($orders as $order) {
                    try {
                        $result = $orderService->syncPlacedOrder($order);

                        if (($result['skipped'] ?? false) === true) {
                            $skippedCount++;
                        } else {
                            $successCount++;
                        }
                    } catch (\Throwable $e) {
                        $failureCount++;
                        $errors[] = [
                            'order_id' => $order->id,
                            'reference' => $order->reference ?? 'N/A',
                            'error' => $e->getMessage(),
                        ];
                    }

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders', $totalOrders],
                ['Successfully Synced', $successCount],
                ['Skipped', $skippedCount],
                ['Failed', $failureCount],
            ]
        );

        if ($failureCount > 0) {
            $this->newLine();
            $this->warn("Failed to sync {$failureCount} order(s):");
            $this->table(
                ['Order ID', 'Reference', 'Error'],
                collect($errors)->take(10)->toArray()
            );

            if (count($errors) > 10) {
                $this->info('... and '.(count($errors) - 10).' more errors.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
