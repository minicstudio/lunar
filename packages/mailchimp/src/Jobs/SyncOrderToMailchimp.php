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
use Lunar\Models\Order;

class SyncOrderToMailchimp implements ShouldQueue
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
        public Order $order
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
            ! config('lunar-frontend.mailchimp.sync_orders', true)) {
            return;
        }

        try {
            $ecommerceService->syncOrder($this->order);

            // Delete the cart after successful order sync (abandoned cart no longer needed)
            if ($this->order->cart_id) {
                $ecommerceService->deleteCart((string) $this->order->cart_id);
            }
        } catch (Exception $e) {
            throw new FailedMailchimpSyncException('Mailchimp order sync error for order '.$this->order->id.'. '.$e->getMessage());
        }
    }
}
