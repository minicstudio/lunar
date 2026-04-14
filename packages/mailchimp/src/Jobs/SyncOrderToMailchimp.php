<?php

namespace Lunar\Mailchimp\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;
use Lunar\Models\Order;

class SyncOrderToMailchimp implements ShouldQueue, ShouldBeUnique
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
        $this->tries = config('lunar.mailchimp.retry.max_attempts', 4);
        $this->backoff = config('lunar.mailchimp.retry.backoff', [60, 300, 3600]);
    }

    /**
     * Get the unique ID for the job.
     * Prevents multiple jobs from being queued for the same order.
     */
    public function uniqueId(): string
    {
        return 'mailchimp-order-sync-'.$this->order->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! config('lunar.mailchimp.enabled', false) ||
            ! config('lunar.mailchimp.sync_orders', true)) {
            return;
        }

        $ecommerceService = app(MailchimpEcommerceService::class);

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
