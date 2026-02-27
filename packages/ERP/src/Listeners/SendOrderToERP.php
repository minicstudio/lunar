<?php

namespace Lunar\ERP\Listeners;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Services\ErpService;

class SendOrderToERP implements ShouldQueue
{
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
}
