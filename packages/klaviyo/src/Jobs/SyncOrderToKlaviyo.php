<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoOrderService;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Order;

class SyncOrderToKlaviyo implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    public function __construct(
        public Order $order,
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    public function uniqueId(): string
    {
        return 'klaviyo-order-sync-'.$this->order->id;
    }

    public function handle(): void
    {
        if (! KlaviyoAvailability::orderSyncEnabled()) {
            KlaviyoLogger::debug('Order sync job skipped — enabled or sync_orders off', [
                'order_id' => $this->order->id,
                'enabled' => KlaviyoAvailability::enabled(),
                'sync_orders' => KlaviyoAvailability::syncOrders(),
            ]);

            return;
        }

        KlaviyoLogger::debug('Order sync job started', [
            'order_id' => $this->order->id,
            'attempt' => $this->attempts(),
        ]);

        try {
            app(KlaviyoOrderService::class)->syncPlacedOrder($this->order);

            KlaviyoLogger::debug('Order sync job completed', [
                'order_id' => $this->order->id,
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Order sync job failed', [
                'order_id' => $this->order->id,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo order sync error for order '.$this->order->id.'. '.$e->getMessage()
            );
        }
    }
}
