<?php

namespace Lunar\Mailchimp\Commands;

use Illuminate\Console\Command;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;
use Lunar\Models\Order;

class SyncAllOrdersToMailchimpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailchimp:sync-all-orders
                            {--chunk=50 : Number of orders to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all previous orders from the database to Mailchimp Ecommerce API';

    /**
     * Execute the console command.
     */
    public function handle(MailchimpEcommerceService $ecommerceService): int
    {
        // Check if Mailchimp is enabled
        if (! config('lunar-frontend.mailchimp.enabled', false)) {
            $this->error('Mailchimp integration is not enabled. Set MAILCHIMP_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        if (! config('lunar-frontend.mailchimp.sync_orders', false)) {
            $this->error('Order sync is not enabled. Set MAILCHIMP_SYNC_ORDERS=true in your .env file.');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        // Only sync placed orders
        $orders = Order::where('status', 'completed');
        $totalOrders = $orders->count();

        if ($totalOrders === 0) {
            $this->info('No orders found to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalOrders} order(s) to sync.");
        $this->warn('Note: This will sync orders with their customers to Mailchimp Ecommerce API.');

        if (! $this->confirm('Do you want to proceed with syncing all orders to Mailchimp?', true)) {
            $this->info('Sync cancelled.');

            return self::SUCCESS;
        }

        $this->info('Starting order sync...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalOrders);
        $progressBar->start();

        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        // Process orders in chunks to avoid memory issues
        $orders
            ->with(['user', 'productLines.purchasable.product', 'currency'])
            ->chunk($chunkSize, function ($orders) use ($ecommerceService, &$successCount, &$failureCount, &$errors, $progressBar) {
                foreach ($orders as $order) {
                    try {
                        $ecommerceService->syncOrder($order);
                        $successCount++;
                    } catch (FailedMailchimpSyncException $e) {
                        $failureCount++;
                        $errors[] = [
                            'order_id' => $order->id,
                            'reference' => $order->reference ?? 'N/A',
                            'error' => $e->getMessage(),
                        ];
                    } catch (\Exception $e) {
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

        // Display results
        $this->info("Sync completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders', $totalOrders],
                ['Successfully Synced', $successCount],
                ['Failed', $failureCount],
            ]
        );

        // Display errors if any
        if ($failureCount > 0) {
            $this->newLine();
            $this->warn("Failed to sync {$failureCount} order(s):");
            $this->table(
                ['Order ID', 'Reference', 'Error'],
                collect($errors)->take(10)->toArray()
            );

            if (count($errors) > 10) {
                $this->info('... and ' . (count($errors) - 10) . ' more errors.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
