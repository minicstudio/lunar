<?php

namespace Lunar\ProductFeed;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class ProductFeedCache
{
    /**
     * @var list<string>
     */
    protected array $formats = ['csv', 'xml', 'json'];

    public function remember(string $format, callable $callback): string
    {
        if (! $this->isEnabled()) {
            return (string) $callback();
        }

        return (string) $this->store()->remember(
            $this->cacheKey($format),
            $this->ttl(),
            $callback
        );
    }

    public function forget(string $format): void
    {
        $this->store()->forget($this->cacheKey($format));
    }

    public function forgetAll(): void
    {
        foreach ($this->formats as $format) {
            $this->forget($format);
        }
    }

    public function cacheKey(string $format): string
    {
        $prefix = (string) config('lunar.product-feed.cache.key_prefix', 'lunar.product-feed');

        return $prefix.'.'.$format;
    }

    public function isEnabled(): bool
    {
        return (bool) config('lunar.product-feed.cache.enabled', true);
    }

    public function ttl(): int
    {
        return max(0, (int) config('lunar.product-feed.cache.ttl', 3600));
    }

    protected function store(): Repository
    {
        $store = config('lunar.product-feed.cache.store');

        if (is_string($store) && $store !== '') {
            return Cache::store($store);
        }

        return Cache::store();
    }
}
