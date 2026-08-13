<?php

namespace Lunar\ERP\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lunar\Admin\Livewire\Components\ActivityLogFeed;
use Lunar\ERP\Services\ErpService;
use Lunar\ERP\Support\OrderStatusUpdater;
use Lunar\Locations\Support\LocalityValidator;
use Lunar\Models\Order;
use Throwable;

class ResendOrderToErpAction extends Action
{
    /**
     * Create a new action instance.
     */
    public static function make(?string $name = 'resend_order_to_erp'): static
    {
        return parent::make($name)
            ->label(__('lunarpanel.erp::actions.resend_to_erp.label'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->action(fn ($livewire) => static::handle($livewire->record))
            ->after(fn ($livewire) => $livewire->dispatch(ActivityLogFeed::UPDATED)->to(ActivityLogFeed::class));
    }

    /**
     * Handle the action.
     */
    protected static function handle(Order $order): void
    {
        if (! static::addressIsValid($order)) {
            (new OrderStatusUpdater)->handle($order, [
                'status' => 'invalid-address',
            ]);

            (new OrderStatusUpdater)->handle($order, [
                'status' => 'failed-erp-sync',
            ]);

            Notification::make()
                ->title(__('lunarpanel.erp::actions.resend_to_erp.notification.invalid_address'))
                ->danger()
                ->send();

            return;
        }

        $erpService = app(ErpService::class);
        $enabledProviders = $erpService->getEnabledProviders();

        try {
            $sent = collect($enabledProviders)
                ->map(fn ($provider) => $erpService->sendOrder($provider, $order))
                ->contains(true);
        } catch (Throwable $e) {
            report($e);
            $sent = false;
        }

        if (! $sent) {
            (new OrderStatusUpdater)->handle($order, [
                'status' => 'failed-erp-sync',
            ]);

            Notification::make()
                ->title(__('lunarpanel.erp::actions.resend_to_erp.notification.failed'))
                ->danger()
                ->send();

            return;
        }

        (new OrderStatusUpdater)->handle($order, [
            'status' => $order->meta['status_before_erp_failure'] ?? 'confirmed',
        ]);

        $order->meta = collect($order->meta)->except('status_before_erp_failure')->all();
        $order->save();

        Notification::make()
            ->title(__('lunarpanel.erp::actions.resend_to_erp.notification.success'))
            ->success()
            ->send();
    }

    /**
     * Whether the order's shipping address matches a known locality.
     * Returns true only when locality data isn't seeded at all.
     */
    protected static function addressIsValid(Order $order): bool
    {
        if (! LocalityValidator::isAvailable()) {
            return true;
        }

        $shippingAddress = $order->shippingAddress;

        return $shippingAddress
            && $shippingAddress->city
            && LocalityValidator::matches($shippingAddress->city, $shippingAddress->state);
    }
}
