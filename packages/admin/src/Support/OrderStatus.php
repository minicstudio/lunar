<?php

namespace Lunar\Admin\Support;

use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Lang;

class OrderStatus
{
    protected static array $cachedStatusColor = [];

    protected static array $cachedStatusLabel = [];

    public static function getLabel($status): string
    {
        if (array_key_exists($status, static::$cachedStatusLabel)) {
            return static::$cachedStatusLabel[$status];
        }

        $label = config('lunar.orders.statuses.'.$status.'.label');

        if (filled($label)) {
            return static::$cachedStatusLabel[$status] = $label;
        }

        $key = 'lunarpanel::order.statuses.'.$status;

        if (filled($status) && Lang::has($key)) {
            return static::$cachedStatusLabel[$status] = $key;
        }

        return static::$cachedStatusLabel[$status] = filled($status) ? $status : 'N/A';
    }

    public static function getColor($status): array
    {
        return static::$cachedStatusColor[$status] ??= Color::generateV3Palette(filled($color = config('lunar.orders.statuses.'.$status.'.color')) ? $color : '#7C7C7C');
    }
}
