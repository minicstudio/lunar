<?php

namespace Lunar\Mailchimp\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

class SubscribeEmailToMailchimp implements ShouldQueue
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

    public function __construct(
        public string $email,
    ) {
        $this->tries = config('lunar.mailchimp.retry.max_attempts', 4);
        $this->backoff = config('lunar.mailchimp.retry.backoff', [60, 300, 3600]);
    }

    public function handle(): void
    {
        if (! config('lunar.mailchimp.enabled', false)) {
            return;
        }

        try {
            app(MailchimpSubscriberService::class)->subscribe($this->email);
        } catch (Exception $e) {
            throw new FailedMailchimpSyncException(
                'Mailchimp subscribe error for '.$this->email.'. '.$e->getMessage()
            );
        }
    }
}
