<?php

namespace Lunar\Admin\Filament\Resources\OrderResource\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconPosition;
use Lunar\Actions\Orders\GetOrderProductLineEditGates;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Actions\Orders\UpdateOrderShippingPrice;
use Lunar\Admin\Support\Orders\OrderProductLineEditFailure;
use Lunar\Models\Order;
use Throwable;

trait DisplaysShippingInfo
{
    public static function getShippingInfolist(): Section
    {
        return self::callStaticLunarHook('extendShippingInfolist', static::getDefaultShippingInfolist());
    }

    public static function getDefaultShippingInfolist(): Section
    {
        return Section::make()
            ->schema([
                RepeatableEntry::make('shippingLines')
                    ->hiddenLabel()
                    ->contained(false)
                    ->columns(2)
                    ->columnSpan(12)
                    ->extraAttributes(['class' => 'lunar-shipping-strip'])
                    ->schema([
                        TextEntry::make('description')
                            ->icon('heroicon-s-truck')
                            ->html()
                            ->iconPosition(IconPosition::Before)
                            ->hiddenLabel()
                            ->wrap(false)
                            ->extraEntryWrapperAttributes(['class' => 'lunar-shipping-nowrap']),
                        TextEntry::make('sub_total')
                            ->hiddenLabel()
                            ->alignEnd()
                            ->wrap(false)
                            ->extraEntryWrapperAttributes(['class' => 'lunar-shipping-nowrap'])
                            ->formatStateUsing(fn ($state) => $state->formatted)
                            ->afterContent(static::modifyShippingPriceAction()),
                        TextEntry::make('notes')
                            ->hidden(
                                fn ($state) => ! $state
                            )
                            ->placeholder(
                                __('lunarpanel::order.infolist.notes.placeholder')
                            ),
                    ]),
            ]);
    }

    /**
     * Pencil link beside the shipping price. The entry schema renders it as a link.
     */
    protected static function modifyShippingPriceAction(): Action
    {
        return Action::make('modify_shipping_price')
            ->label(__('lunarpanel::order.action.modify_shipping_price.label'))
            ->icon('heroicon-o-pencil')
            ->extraAttributes([
                'style' => 'white-space: nowrap; flex-shrink: 0;',
            ], merge: true)
            ->modalHeading(__('lunarpanel::order.action.modify_shipping_price.label'))
            ->fillForm(function (mixed $livewire): array {
                $line = $livewire->record?->shippingLines()->first();
                $currency = $livewire->record?->currency;
                $factor = max(1, (int) ($currency?->factor ?: 1));
                $places = (int) ($currency?->decimal_places ?? 2);
                $minor = (int) ($line?->sub_total->value ?? 0);

                return [
                    'amount' => number_format($minor / $factor, $places, '.', ''),
                ];
            })
            ->schema([
                TextInput::make('amount')
                    ->label(__('lunarpanel::order.action.modify_shipping_price.amount.label'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(function (mixed $livewire): string {
                        $currency = $livewire->record?->currency;
                        $factor = max(1, (int) ($currency?->factor ?: 1));
                        $places = (int) ($currency?->decimal_places ?? 2);

                        return number_format(1 / $factor, $places, '.', '');
                    })
                    ->default(function (mixed $livewire): string {
                        $line = $livewire->record?->shippingLines()->first();
                        $currency = $livewire->record?->currency;
                        $factor = max(1, (int) ($currency?->factor ?: 1));
                        $places = (int) ($currency?->decimal_places ?? 2);
                        $minor = (int) ($line?->sub_total->value ?? 0);

                        return number_format($minor / $factor, $places, '.', '');
                    }),
            ])
            ->action(function (array $data, Action $action, mixed $livewire): void {
                $order = $livewire->record;

                if (! $order instanceof Order) {
                    return;
                }

                $factor = max(1, (int) ($order->currency?->factor ?: 1));

                try {
                    UpdateOrderShippingPrice::run($order, (int) round(((float) $data['amount']) * $factor));
                } catch (Throwable $exception) {
                    $title = OrderProductLineEditFailure::notificationTitle($exception);

                    if ($title === null) {
                        throw $exception;
                    }

                    Notification::make()
                        ->title($title)
                        ->danger()
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->title(__('lunarpanel::order.action.modify_shipping_price.notification'))
                    ->success()
                    ->send();

                $livewire->record = $order->fresh();
                $livewire->dispatch('order-product-lines-updated');
            })
            ->visible(function (mixed $livewire): bool {
                $order = $livewire->record;

                if (! $order instanceof Order) {
                    return false;
                }

                return UpdateOrderProductLines::userCanManageOrders()
                    && GetOrderProductLineEditGates::run($order) === []
                    && $order->shippingLines()->count() === 1;
            });
    }
}
