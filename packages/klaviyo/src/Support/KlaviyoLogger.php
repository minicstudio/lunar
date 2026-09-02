<?php

namespace Lunar\Klaviyo\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class KlaviyoLogger
{
    /**
     * Always-on informational log (useful for catalog sync troubleshooting).
     *
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info('[Klaviyo] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function debug(string $message, array $context = []): void
    {
        if (! config('lunar.klaviyo.debug', false)) {
            return;
        }

        Log::info('[Klaviyo] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning('[Klaviyo] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = [], ?Throwable $exception = null): void
    {
        if ($exception !== null) {
            $context['exception'] = $exception->getMessage();
        }

        Log::error('[Klaviyo] '.$message, $context);
    }
}
