<?php

namespace Lunar\Klaviyo\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Persists catalog item external_id by Lunar product id so DELETE can target
 * the remote item after variants (and SKUs) are gone.
 */
final class CatalogExternalIdStore
{
    private const CACHE_PREFIX = 'lunar.klaviyo.catalog.item_external_id.';

    public static function remember(int $productId, string $itemExternalId): void
    {
        if ($itemExternalId === '') {
            return;
        }

        Cache::forever(self::CACHE_PREFIX.$productId, $itemExternalId);
    }

    public static function rememberIfAbsent(int $productId, string $itemExternalId): void
    {
        if (self::get($productId) !== null) {
            return;
        }

        self::remember($productId, $itemExternalId);
    }

    public static function get(int $productId): ?string
    {
        $value = Cache::get(self::CACHE_PREFIX.$productId);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function forget(int $productId): void
    {
        Cache::forget(self::CACHE_PREFIX.$productId);
    }
}
