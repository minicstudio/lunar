<?php

namespace Lunar\Mailchimp\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;
use Lunar\Models\Cart;

class SyncCartToMailchimp implements ShouldQueue
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
        public Cart $cart
    ) {
        $this->tries = config('lunar.mailchimp.retry.max_attempts', 4);
        $this->backoff = config('lunar.mailchimp.retry.backoff', [60, 300, 3600]);
    }

    /**
     * Execute the job.
     */
    public function handle(MailchimpEcommerceService $ecommerceService): void
    {
        if (! config('lunar.mailchimp.enabled', false) ||
            ! config('lunar.mailchimp.sync_carts', true)) {
            return;
        }

        // Only sync carts with associated users (logged in)
        if (! $this->cart->user_id) {
            return;
        }

        // Don't sync empty carts
        if ($this->cart->lines->isEmpty()) {
            try {
                $ecommerceService->deleteCart((string) $this->cart->id);
            } catch (Exception $e) {
                // Cart may not exist in Mailchimp yet
            }

            return;
        }

        try {
            $ecommerceService->syncCart($this->cart);
        } catch (Exception $e) {
            throw new FailedMailchimpSyncException('Mailchimp cart sync error for cart '.$this->cart->id.'. '.$e->getMessage());
        }
    }
}
