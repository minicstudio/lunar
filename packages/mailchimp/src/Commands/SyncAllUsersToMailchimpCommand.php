<?php

namespace Lunar\Mailchimp\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

class SyncAllUsersToMailchimpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailchimp:sync-all-users
                            {--chunk=100 : Number of users to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all users from the database to Mailchimp with their first and last names';

    /**
     * Execute the console command.
     */
    public function handle(MailchimpSubscriberService $subscriberService): int
    {
        // Check if Mailchimp is enabled
        if (! config('lunar-frontend.mailchimp.enabled', false)) {
            $this->error('Mailchimp integration is not enabled. Set MAILCHIMP_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        $totalUsers = Authenticatable::count();

        if ($totalUsers === 0) {
            $this->info('No users found to sync.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalUsers} user(s) to sync.");

        if (! $this->confirm('Do you want to proceed with syncing all users to Mailchimp?', true)) {
            $this->info('Sync cancelled.');

            return self::SUCCESS;
        }

        $this->info('Starting user sync...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        // Process users in chunks to avoid memory issues
        Authenticatable::chunk($chunkSize, function ($users) use ($subscriberService, &$successCount, &$failureCount, &$errors, $progressBar) {
            foreach ($users as $user) {
                try {
                    $subscriberService->syncSubscriber($user);
                    $successCount++;
                } catch (FailedMailchimpSyncException $e) {
                    $failureCount++;
                    $errors[] = [
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ];
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = [
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ];
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users', $totalUsers],
                ['Successfully Synced', $successCount],
                ['Failed', $failureCount],
            ]
        );

        // Display errors if any
        if ($failureCount > 0) {
            $this->newLine();
            $this->warn("Failed to sync {$failureCount} user(s):");
            $this->table(
                ['Email', 'Error'],
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
