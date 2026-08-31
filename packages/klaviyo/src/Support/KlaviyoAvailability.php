<?php

namespace Lunar\Klaviyo\Support;

final class KlaviyoAvailability
{
    public static function enabled(): bool
    {
        return (bool) config('lunar.klaviyo.enabled', false);
    }

    public static function syncProducts(): bool
    {
        return (bool) config('lunar.klaviyo.sync_products', false);
    }

    public static function syncOrders(): bool
    {
        return (bool) config('lunar.klaviyo.sync_orders', false);
    }

    public static function syncSubscribers(): bool
    {
        return (bool) config('lunar.klaviyo.sync_subscribers', false);
    }

    public static function trackEvents(): bool
    {
        return (bool) config('lunar.klaviyo.track_events', true);
    }

    public static function catalogSyncEnabled(): bool
    {
        return self::enabled() && self::syncProducts();
    }

    public static function orderSyncEnabled(): bool
    {
        return self::enabled() && self::syncOrders();
    }

    public static function subscriberSyncEnabled(): bool
    {
        return self::enabled() && self::syncSubscribers();
    }

    public static function eventTrackingEnabled(): bool
    {
        return self::enabled() && self::trackEvents();
    }
}
