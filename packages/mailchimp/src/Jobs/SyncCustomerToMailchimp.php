<?php

namespace Lunar\Mailchimp\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;

class SyncCustomerToMailchimp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff;

    /**
     * The job's constructor.
     */
    public function __construct(
        public Authenticatable $user
    ) {
        $this->tries = config('lunar-frontend.mailchimp.retry.max_attempts', 4);
        $this->backoff = config('lunar-frontend.mailchimp.retry.backoff', [60, 300, 3600]);
    }

    /**
     * Execute the job.
     */
    public function handle(MailchimpEcommerceService $ecommerceService): void
    {
        if (! config('lunar-frontend.mailchimp.enabled', false) ||
            ! config('lunar-frontend.mailchimp.sync_customers.enabled', false)) {
            return;
        }

        try {
            $ecommerceService->syncCustomer($this->user);
        } catch (Exception $e) {
            throw new FailedMailchimpSyncException('Mailchimp customer sync error for user '.$this->user->id.'. '.$e->getMessage());
        }
    }
}
