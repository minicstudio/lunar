<?php

namespace Lunar\Content\Support;

class StorefrontColors
{
    /**
     * Resolve the storefront primary color for admin defaults.
     */
    public static function primary(): string
    {
        return config('lunar.content.announcement_default_bg_color')
            ?? config('lunar-frontend.brand.primary_color')
            ?? config('lunar-frontend.payment.colors.primary.600')
            ?? config('lunar-frontend.payment.colors.primary.500')
            ?? '#4F46E5';
    }

    /**
     * Resolve the default announcement text color for admin defaults.
     */
    public static function text(): string
    {
        return config('lunar.content.announcement_default_text_color')
            ?? '#ffffff';
    }
}
