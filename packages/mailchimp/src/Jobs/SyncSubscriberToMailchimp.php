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
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

class SyncSubscriberToMailchimp implements ShouldQueue
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
     * Additional merge fields to include.
     */
    protected array $mergeFields;

    /**
     * The job's constructor.
     */
    public function __construct(
        public Authenticatable $user,
        array $mergeFields = []
    ) {
        $this->tries = config('lunar.mailchimp.retry.max_attempts', 4);
        $this->backoff = config('lunar.mailchimp.retry.backoff', [60, 300, 3600]);
        $this->mergeFields = $mergeFields;
    }

    /**
     * Execute the job.
     */
    public function handle(MailchimpSubscriberService $subscriberService): void
    {
        if (! config('lunar.mailchimp.enabled', false)) {
            return;
        }

        try {
            $subscriberService->syncSubscriber($this->user, $this->mergeFields);
        } catch (Exception $e) {
            throw new FailedMailchimpSyncException('Mailchimp subscriber sync error for user '.$this->user->id.'. '.$e->getMessage());
        }
    }
}
