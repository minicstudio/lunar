<?php

namespace Lunar\Mailchimp\Commands;

use Illuminate\Console\Command;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Exceptions\MissingMailchimpConfigurationException;
use Lunar\Mailchimp\Services\MailchimpService;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

class CreateMailchimpStoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailchimp:create-store
                            {--store-id= : The unique store ID (defaults to MAILCHIMP_STORE_ID from env)}
                            {--name= : The store name (defaults to APP_NAME)}
                            {--domain= : The store domain (defaults to APP_URL)}
                            {--email= : The store email (defaults to MAIL_FROM_ADDRESS)}
                            {--currency= : The default currency code (defaults to first currency in database)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Mailchimp store for Ecommerce API integration';

    /**
     * Execute the console command.
     */
    public function handle(MailchimpService $mailchimpService, MailchimpSubscriberService $subscriberService): int
    {
        try {
            // Check if Mailchimp is enabled
            if (! config('lunar-frontend.mailchimp.enabled', false)) {
                $this->error('Mailchimp integration is not enabled. Set MAILCHIMP_ENABLED=true in your .env file.');

                return self::FAILURE;
            }

            // Get parameters with defaults
            $storeName = $this->option('name') ?? config('app.name');
            $domain = $this->option('domain') ?? parse_url(config('app.url'), PHP_URL_HOST);
            $emailAddress = $this->option('email') ?? config('mail.from.address');
            $currencyCode = $this->option('currency') ?? $this->getDefaultCurrencyCode();

            // Get or generate store ID
            $storeId = $this->option('store-id');
            if (empty($storeId)) {
                // Auto-generate a store ID based on domain
                $storeId = $this->generateStoreId($domain);
                $this->info("Auto-generated Store ID: {$storeId}");

                if (! $this->confirm('Use this Store ID?', true)) {
                    $storeId = $this->ask('Enter your preferred Store ID (alphanumeric, max 50 chars)');
                }
            }

            // Validate store ID format
            if (empty($storeId) || strlen($storeId) > 50 || ! preg_match('/^[a-zA-Z0-9_-]+$/', $storeId)) {
                $this->error('Invalid Store ID. Must be alphanumeric (with _ or -), max 50 characters.');

                return self::FAILURE;
            }

            if (empty($emailAddress)) {
                $this->error('Email address is required. Provide it via --email option or set MAIL_FROM_ADDRESS in .env');

                return self::FAILURE;
            }

            if (empty($currencyCode)) {
                $this->error('Currency code is required. Provide it via --currency option or ensure you have currencies in the database');

                return self::FAILURE;
            }

            $this->info('Creating Mailchimp store with the following details:');
            $this->table(
                ['Parameter', 'Value'],
                [
                    ['Store ID', $storeId],
                    ['Store Name', $storeName],
                    ['Domain', $domain],
                    ['Email', $emailAddress],
                    ['Currency', $currencyCode],
                ]
            );

            if (! $this->confirm('Do you want to proceed?', true)) {
                $this->info('Store creation cancelled.');

                return self::SUCCESS;
            }

            $this->info('Creating store...');

            $result = $mailchimpService->createStore(
                $storeId,
                $storeName,
                $domain,
                $emailAddress,
                $currencyCode
            );

            $this->newLine();
            $this->info('✓ Store created successfully!');
            $this->newLine();

            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $result['id'] ?? 'N/A'],
                    ['Name', $result['name'] ?? 'N/A'],
                    ['Domain', $result['domain'] ?? 'N/A'],
                    ['Currency', $result['currency_code'] ?? 'N/A'],
                    ['Created At', $result['created_at'] ?? 'N/A'],
                ]
            );

            // Setup merge fields
            $this->newLine();
            $this->info('Setting up merge fields...');

            $mergeFieldResults = $subscriberService->setupMergeFields();

            $successCount = count(array_filter($mergeFieldResults, fn ($r) => $r['success']));
            $totalCount = count($mergeFieldResults);

            if ($successCount === $totalCount) {
                $this->info("✓ All {$totalCount} merge fields created/updated successfully!");
            } else {
                $this->warn("⚠ {$successCount}/{$totalCount} merge fields created/updated successfully");

                foreach ($mergeFieldResults as $tag => $result) {
                    if (! $result['success']) {
                        $this->error("  × {$tag}: {$result['error']}");
                    }
                }
            }

            $this->newLine();
            $this->info('Add this to your .env file if not already present:');
            $this->line("MAILCHIMP_STORE_ID={$storeId}");

            return self::SUCCESS;
        } catch (MissingMailchimpConfigurationException $e) {
            $this->error('Missing Mailchimp configuration: '.$e->getMessage());
            $this->newLine();
            $this->info('Make sure you have set the following in your .env file:');
            $this->line('MAILCHIMP_ENABLED=true');
            $this->line('MAILCHIMP_API_KEY=your-api-key');
            $this->line('MAILCHIMP_LIST_ID=your-list-id');

            return self::FAILURE;
        } catch (FailedMailchimpSyncException $e) {
            $this->error('Failed to create store: '.$e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get the default currency code from the database.
     */
    protected function getDefaultCurrencyCode(): ?string
    {
        try {
            $currency = \Lunar\Models\Currency::where('default', true)->first()
                ?? \Lunar\Models\Currency::first();

            return $currency?->code;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate a store ID based on the domain.
     */
    protected function generateStoreId(string $domain): string
    {
        // Remove www. prefix and special chars, replace dots with dashes
        $id = preg_replace('/^www\./', '', $domain);
        $id = preg_replace('/[^a-zA-Z0-9-]/', '-', $id);
        $id = trim($id, '-');

        // Limit to 50 characters
        if (strlen($id) > 50) {
            $id = substr($id, 0, 50);
        }

        return strtolower($id);
    }
}
