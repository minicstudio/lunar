<?php

namespace Lunar\Admin\Filament\Resources\OrderResource\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Collection;
use Lunar\Actions\Orders\GetOrderProductLineEditGates;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Actions\Orders\UpdateOrderShippingMethod;
use Lunar\Actions\Orders\UpdateOrderShippingPrice;
use Lunar\Admin\Support\Orders\OrderProductLineEditFailure;
use Lunar\DataTypes\ShippingOption;
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
                            ->extraEntryWrapperAttributes(['class' => 'lunar-shipping-nowrap lunar-shipping-method'])
                            ->belowContent(static::modifyShippingMethodAction()),
                        TextEntry::make('sub_total')
                            ->hiddenLabel()
                            ->alignEnd()
                            ->wrap(false)
                            ->extraEntryWrapperAttributes([
                                'class' => 'lunar-shipping-nowrap lunar-shipping-price',
                                'style' => 'margin-inline-start: auto; width: max-content; text-align: right;',
                            ])
                            ->extraAttributes([
                                'style' => 'display: block; width: 100%; text-align: right;',
                            ], merge: true)
                            ->formatStateUsing(fn ($state) => $state->formatted)
                            ->belowContent(static::modifyShippingPriceAction()),
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
     * Pencil link under the shipping method name.
     */
    protected static function modifyShippingMethodAction(): Action
    {
        return Action::make('modify_shipping_method')
            ->label(__('lunarpanel::order.action.modify_shipping_method.label'))
            ->icon('heroicon-o-pencil')
            ->extraAttributes([
                'style' => 'white-space: nowrap; flex-shrink: 0;',
            ], merge: true)
            ->modalHeading(__('lunarpanel::order.action.modify_shipping_method.label'))
            ->fillForm(function (mixed $livewire): array {
                $order = $livewire->record;

                if (! $order instanceof Order) {
                    return ['identifier' => null];
                }

                $current = (string) $order->shippingLines()->value('identifier');
                $offered = self::offeredShippingMethodOptions($order)
                    ->contains(fn (ShippingOption $option): bool => $option->getIdentifier() === $current);

                return [
                    'identifier' => $offered ? $current : null,
                ];
            })
            ->schema([
                Select::make('identifier')
                    ->label(__('lunarpanel::order.action.modify_shipping_method.method.label'))
                    ->required()
                    ->options(function (mixed $livewire): array {
                        $order = $livewire->record;

                        if (! $order instanceof Order) {
                            return [];
                        }

                        return self::shippingMethodOptionLabels(
                            self::offeredShippingMethodOptions($order)
                        );
                    }),
            ])
            ->action(function (array $data, Action $action, mixed $livewire): void {
                $order = $livewire->record;

                if (! $order instanceof Order) {
                    return;
                }

                $update = UpdateOrderShippingMethod::make();

                try {
                    $saved = $update->execute($order, (string) $data['identifier']);
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

                    return;
                }

                Notification::make()
                    ->title(__('lunarpanel::order.action.modify_shipping_method.notification'))
                    ->success()
                    ->send();

                if ($update->changedPickupToAddressDelivery && self::addressesMatch($saved->shippingAddress, $saved->billingAddress)) {
                    Notification::make()
                        ->title(__('lunarpanel::order.action.modify_shipping_method.billing_address_warning'))
                        ->warning()
                        ->send();
                }

                $livewire->record = $saved;
                $livewire->dispatch('order-product-lines-updated');
            })
            ->visible(fn (mixed $livewire): bool => self::shippingActionsVisible($livewire));
    }

    /**
     * Options for the method modal.
     *
     * A known edit failure is shown as a notification, and the select stays empty.
     * Opening the modal reads this once per order.
     *
     * @return Collection<int, ShippingOption>
     */
    protected static function offeredShippingMethodOptions(Order $order): Collection
    {
        $cacheKey = 'lunar.order_shipping_method.modal_options';

        /** @var array<int|string, Collection<int, ShippingOption>> $cache */
        $cache = app()->bound($cacheKey) ? app($cacheKey) : [];
        $key = $order->getKey();

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $options = UpdateOrderShippingMethod::make()->offeredOptions($order);
        } catch (Throwable $exception) {
            $title = OrderProductLineEditFailure::notificationTitle($exception);

            if ($title === null) {
                throw $exception;
            }

            Notification::make()
                ->title($title)
                ->danger()
                ->send();

            $options = collect();
        }

        $cache[$key] = $options;
        app()->instance($cacheKey, $cache);

        return $options;
    }

    /**
     * @param  Collection<int, ShippingOption>  $options
     * @return array<string, string>
     */
    protected static function shippingMethodOptionLabels(Collection $options): array
    {
        $names = $options->countBy(fn (ShippingOption $option): string => $option->getName());
        $labels = [];

        foreach ($options as $option) {
            $label = $option->getName();
            $zone = $option->getOption();

            if (($names[$label] ?? 0) > 1 && filled($zone)) {
                $label .= ' ('.$zone.')';
            }

            $labels[$option->getIdentifier()] = $label;
        }

        return $labels;
    }

    /**
     * Pencil link under the shipping price. The entry schema renders it as a link.
     */
    protected static function modifyShippingPriceAction(): Action
    {
        return Action::make('modify_shipping_price')
            ->label(__('lunarpanel::order.action.modify_shipping_price.label'))
            ->icon('heroicon-o-pencil')
            ->extraAttributes([
                'style' => 'white-space: nowrap; flex-shrink: 0; margin-inline-start: auto;',
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
            ->visible(fn (mixed $livewire): bool => self::shippingActionsVisible($livewire));
    }

    protected static function shippingActionsVisible(mixed $livewire): bool
    {
        $order = $livewire->record;

        if (! $order instanceof Order) {
            return false;
        }

        return UpdateOrderProductLines::userCanManageOrders()
            && GetOrderProductLineEditGates::run($order) === []
            && $order->shippingLines()->count() === 1;
    }
}
