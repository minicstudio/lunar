<?php

namespace Lunar\Mailchimp\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;
use Lunar\Models\Customer;

class SyncAllUserLanguagesToMailchimpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailchimp:sync-user-languages
                            {--chunk=100 : Number of customers to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the LANGUAGE merge field to Mailchimp for all customers with a locale set';

    /**
     * Execute the console command.
     */
    public function handle(MailchimpSubscriberService $subscriberService): int
    {
        if (! config('lunar.mailchimp.enabled', false)) {
            $this->error('Mailchimp integration is not enabled. Set MAILCHIMP_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        $query = Customer::query()->whereHas('users', function (Builder $query): void {
            $query->whereNotNull('locale')->where('locale', '!=', '');
        });

        $totalCustomers = $query->count();

        if ($totalCustomers === 0) {
            $this->info('No customers with a locale found to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCustomers} customer(s) with a locale to sync.");

        if (! $this->confirm('Do you want to proceed with syncing user languages to Mailchimp?', true)) {
            $this->info('Sync cancelled.');

            return self::SUCCESS;
        }

        $this->info('Starting language sync...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalCustomers);
        $progressBar->start();

        $successCount = 0;
        $skippedCount = 0;
        $failureCount = 0;
        $errors = [];

        $query->chunk($chunkSize, function ($customers) use ($subscriberService, &$successCount, &$skippedCount, &$failureCount, &$errors, $progressBar): void {
            foreach ($customers as $customer) {
                try {
                    $result = $subscriberService->syncSubscriberLanguage($customer);

                    if ($result === null) {
                        $skippedCount++;
                    } else {
                        $successCount++;
                    }
                } catch (FailedMailchimpSyncException $e) {
                    $failureCount++;
                    $errors[] = [
                        'customer' => $customer->first_name.' '.$customer->last_name,
                        'error' => $e->getMessage(),
                    ];
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = [
                        'customer' => $customer->first_name.' '.$customer->last_name,
                        'error' => $e->getMessage(),
                    ];
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Language sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Customers', $totalCustomers],
                ['Successfully Synced', $successCount],
                ['Skipped', $skippedCount],
                ['Failed', $failureCount],
            ]
        );

        if ($failureCount > 0) {
            $this->newLine();
            $this->warn("Failed to sync {$failureCount} customer(s):");
            $this->table(
                ['Customer', 'Error'],
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
