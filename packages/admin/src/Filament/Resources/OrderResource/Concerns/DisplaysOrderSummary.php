<?php

namespace Lunar\Admin\Filament\Resources\OrderResource\Concerns;

use Carbon\Carbon;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Facades\Lang;
use Lunar\Admin\Support\OrderStatus;
use Lunar\Models\Order;

trait DisplaysOrderSummary
{
    public static function getDefaultOrderSummaryNewCustomerEntry(): TextEntry
    {
        return TextEntry::make('new_customer')
            ->label(__('lunarpanel::order.infolist.new_returning.label'))
            ->alignEnd()
            ->formatStateUsing(fn ($state) => __('lunarpanel::order.infolist.'.($state ? 'new' : 'returning').'_customer.label'));
    }

    public static function getOrderSummaryNewCustomerEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryNewCustomerEntry', static::getDefaultOrderSummaryNewCustomerEntry());
    }

    /**
     * Build right-aligned entries for visible customer metadata.
     *
     * @return Group
     */
    public static function getCustomerDetailsEntry(): Group
    {
        return Group::make()
            ->key('customer_details')
            ->schema(fn (?Order $record): array => collect(static::getCustomerOrderMetaKeys())
                ->diff(static::getHiddenOrderMetaKeys())
                ->filter(fn (string $key): bool => filled(data_get($record?->meta, $key)))
                ->map(fn (string $key): Entry => static::getOrderMetaEntry($key, data_get($record?->meta, $key))->alignEnd())
                ->values()
                ->all());
    }

    /**
     * Return metadata keys hidden from the order page, including extensions.
     *
     * @return array<int, string>
     */
    protected static function getHiddenOrderMetaKeys(): array
    {
        return self::callStaticLunarHook('extendHiddenOrderMetaKeys', [
            'stashed_courier_address',
            'stashed_locker_address',
            'stashed_pickup_address',
            'preloaded_from_order_id',
            'insights_purchase_pushed_at',
            'gtm_purchase_event_id',
            'gtm_purchase_datalayer_pushed_at',
            'gtm_ga4_purchase_fallback_sent_at',
            'gtm_refund_sent_at',
            'ga4_client_id',
            'ga4_session_id',
            'ga4_engagement_time_msec',
            'gift_slot_allocations',
        ]);
    }

    /**
     * Return customer metadata keys in display order, including extensions.
     *
     * @return array<int, string>
     */
    protected static function getCustomerOrderMetaKeys(): array
    {
        return self::callStaticLunarHook('extendCustomerOrderMetaKeys', [
            'is_guest',
            'language_locale',
        ]);
    }

    /**
     * Build an infolist entry with a translated label and type-appropriate formatting.
     *
     * @return Entry
     */
    protected static function getOrderMetaEntry(string $key, mixed $value): Entry
    {
        $name = 'meta_'.$key;
        $label = static::getOrderMetaLabel($key);

        if (is_array($value)) {
            return KeyValueEntry::make($name)
                ->label($label)
                ->state($value);
        }

        if (Carbon::hasFormat((string) $value, 'Y-m-d H:i:s')) {
            $timezone = config('lunar.panel.timezone') ?? config('app.timezone');
            $converted = Carbon::parse($value, config('app.timezone'))
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s');

            return TextEntry::make($name)
                ->state($converted)
                ->label($label)
                ->copyable();
        }

        // Format boolean values as yes/no
        if (is_bool($value)) {
            $formattedValue = $value ? __('lunarpanel::global.yes') : __('lunarpanel::global.no');

            return TextEntry::make($name)
                ->state($formattedValue)
                ->label($label);
        }

        return TextEntry::make($name)
            ->state(static::formatOrderMetaValue($key, $value))
            ->label($label)
            ->copyable()
            ->limit(50)->tooltip(function (TextEntry $component): ?string {
                $state = $component->getState();
                if (strlen($state) <= $component->getCharacterLimit()) {
                    return null;
                }

                return $state;
            });
    }

    /**
     * Resolve a metadata label using its panel translation when available.
     *
     * @return string
     */
    protected static function getOrderMetaLabel(string $key): string
    {
        $translationKey = "lunarpanel::order.infolist.meta.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : __($key);
    }

    /**
     * Translate known shipping and payment values while preserving unrecognized values.
     *
     * @return mixed
     */
    protected static function formatOrderMetaValue(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $translationKey = match ($key) {
            'shippingType' => "lunarpanel::order.infolist.shipping_types.{$value}",
            'payment_option', 'payment_type' => "lunarpanel::order.infolist.payment_methods.{$value}",
            default => null,
        };

        return $translationKey && Lang::has($translationKey) ? __($translationKey) : $value;
    }

    public static function getDefaultOrderSummaryStatusEntry(): TextEntry
    {
        return TextEntry::make('status')
            ->label(__('lunarpanel::order.infolist.status.label'))
            ->formatStateUsing(fn ($state) => __(OrderStatus::getLabel($state)))
            ->alignEnd()
            ->color(fn ($state) => OrderStatus::getColor($state))
            ->badge();
    }

    public static function getOrderSummaryStatusEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryStatusEntry', static::getDefaultOrderSummaryStatusEntry());
    }

    public static function getDefaultOrderReferenceEntry(): TextEntry
    {
        return TextEntry::make('reference')
            ->label(__('lunarpanel::order.infolist.reference.label'))
            ->alignEnd()
            ->icon('heroicon-o-clipboard')
            ->iconPosition(IconPosition::After)
            ->copyable();
    }

    public static function getOrderSummaryReferenceEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryReferenceEntry', static::getDefaultOrderReferenceEntry());
    }

    public static function getDefaultOrderSummaryCustomerReferenceEntry(): TextEntry
    {
        return TextEntry::make('customer_reference')
            ->label(__('lunarpanel::order.infolist.customer_reference.label'))
            ->alignEnd()
            ->icon('heroicon-o-clipboard')
            ->iconPosition(IconPosition::After)
            ->copyable();
    }

    public static function getOrderSummaryCustomerReferenceEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryCustomerReferenceEntry', static::getDefaultOrderSummaryCustomerReferenceEntry());
    }

    public static function getDefaultOrderSummaryChannelEntry(): TextEntry
    {
        return TextEntry::make('channel.name')
            ->label(__('lunarpanel::order.infolist.channel.label'))
            ->alignEnd();
    }

    public static function getOrderSummaryChannelEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryChannelEntry', static::getDefaultOrderSummaryChannelEntry());
    }

    public static function getDefaultOrderSummaryCreatedAtEntry(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('lunarpanel::order.infolist.date_created.label'))
            ->alignEnd()
            ->dateTime('Y-m-d h:i a', timezone: config('lunar.panel.timezone'))
            ->visible(fn ($record) => ! $record->placed_at);
    }

    public static function getOrderSummaryCreatedAtEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryCreatedAtEntry', static::getDefaultOrderSummaryCreatedAtEntry());
    }

    public static function getDefaultOrderSummaryPlacedAtEntry(): TextEntry
    {
        return TextEntry::make('placed_at')
            ->label(__('lunarpanel::order.infolist.date_placed.label'))
            ->alignEnd()
            ->dateTime('Y-m-d h:i a', timezone: config('lunar.panel.timezone'))
            ->placeholder('-');
    }

    public static function getOrderSummaryPlacedAtEntry(): Entry
    {
        return self::callStaticLunarHook('extendOrderSummaryPlacedAtEntry', static::getDefaultOrderSummaryPlacedAtEntry());
    }

    public static function getOrderSummarySchema(): array
    {
        return self::callStaticLunarHook('extendOrderSummarySchema', [
            static::getOrderSummaryNewCustomerEntry(),
            static::getCustomerDetailsEntry(),
            static::getOrderSummaryStatusEntry(),
            static::getOrderSummaryReferenceEntry(),
            static::getOrderSummaryCustomerReferenceEntry(),
            static::getOrderSummaryChannelEntry(),
            static::getOrderSummaryCreatedAtEntry(),
            static::getOrderSummaryPlacedAtEntry(),
        ]);
    }

    public static function getDefaultOrderSummaryInfolist(): Section
    {
        return Section::make()
            ->compact()
            ->inlineLabel()
            ->schema(
                static::getOrderSummarySchema()
            );
    }

    public static function getOrderSummaryInfolist(): Section
    {
        return self::callStaticLunarHook('extendOrderSummaryInfolist', static::getDefaultOrderSummaryInfolist());
    }
}
