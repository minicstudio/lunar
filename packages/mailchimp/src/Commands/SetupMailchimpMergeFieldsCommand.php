<?php

namespace Lunar\Mailchimp\Commands;

use Illuminate\Console\Command;
use Lunar\Mailchimp\Exceptions\MissingMailchimpConfigurationException;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

class SetupMailchimpMergeFieldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailchimp:setup-merge-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create/update merge fields in Mailchimp audience for contact details and preferences';

    /**
     * Execute the console command.
     */
    public function handle(MailchimpSubscriberService $subscriberService): int
    {
        try {
            // Check if Mailchimp is enabled
            if (! config('lunar.mailchimp.enabled', false)) {
                $this->error('Mailchimp integration is not enabled. Set MAILCHIMP_ENABLED=true in your .env file.');

                return self::FAILURE;
            }

            $this->info('Setting up Mailchimp merge fields...');
            $this->newLine();

            $results = $subscriberService->setupMergeFields();

            $this->table(
                ['Tag', 'Status', 'Details'],
                collect($results)->map(function ($result, $tag) {
                    return [
                        $tag,
                        $result['success'] ? '✓ Success' : '✗ Failed',
                        $result['success']
                            ? ($result['data']['name'] ?? 'Created/Updated')
                            : $result['error'],
                    ];
                })->toArray()
            );

            $successCount = count(array_filter($results, fn ($r) => $r['success']));
            $totalCount = count($results);

            $this->newLine();

            if ($successCount === $totalCount) {
                $this->info("✓ All {$totalCount} merge fields created/updated successfully!");

                return self::SUCCESS;
            } else {
                $this->warn("⚠ {$successCount}/{$totalCount} merge fields created/updated successfully");

                return self::FAILURE;
            }
        } catch (MissingMailchimpConfigurationException $e) {
            $this->error('Missing Mailchimp configuration: '.$e->getMessage());
            $this->newLine();
            $this->info('Make sure you have set the following in your .env file:');
            $this->line('MAILCHIMP_ENABLED=true');
            $this->line('MAILCHIMP_API_KEY=your-api-key');
            $this->line('MAILCHIMP_LIST_ID=your-list-id');

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
