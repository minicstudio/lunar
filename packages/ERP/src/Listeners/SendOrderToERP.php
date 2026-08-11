<?php

namespace Lunar\ERP\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Services\ErpService;
use Lunar\ERP\Support\OrderStatusUpdater;
use Lunar\Models\Order;
use Throwable;

class SendOrderToERP implements ShouldQueue
{
    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying, per attempt.
     */
    public array $backoff = [60, 300, 900];

    /**
     * Handle the event.
     */
    public function handle(OrderPlacedEvent $event): void
    {
        if (! config('lunar.erp.enabled')) {
            return;
        }

        $erpService = app(ErpService::class);
        $enabledProviders = $erpService->getEnabledProviders();

        if (empty($enabledProviders)) {
            return;
        }

        foreach ($enabledProviders as $provider) {
            $erpService->sendOrder($provider, $event->order);
        }
    }

    /**
     * Handle a queued listener failure after all attempts are exhausted.
     */
    public function failed(OrderPlacedEvent $event, Throwable $exception): void
    {
        $this->stashStatusBeforeFailure($event->order);

        (new OrderStatusUpdater)->handle($event->order, [
            'status' => 'failed-erp-sync',
        ]);

        report($exception);
    }

    /**
     * Stash the order's status before marking it as failed, so a resend can
     * restore it later. Finds the last "real" status before the most recent invalid-address/failed-erp-sync chain.
     */
    protected function stashStatusBeforeFailure(Order $order): void
    {
        if (isset($order->meta['status_before_erp_failure'])) {
            return;
        }

        $specialStatuses = ['invalid-address', 'failed-erp-sync'];

        $boundaryActivity = $order->getActivitiesByStatuses($specialStatuses)
            ->first(fn ($activity) => ! in_array($activity->properties['previous'] ?? null, $specialStatuses));

        $priorStatus = $boundaryActivity->properties['previous'] ?? $order->status;

        $order->meta = collect($order->meta)->merge([
            'status_before_erp_failure' => $priorStatus,
        ])->all();
        $order->save();
    }
}
