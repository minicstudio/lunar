<?php

namespace Lunar\Analytics\Conversions\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Lunar\Analytics\Conversions\Filament\Widgets\ConversionsOverTimeChart;
use Lunar\Analytics\Conversions\Filament\Widgets\ConversionStatsOverview;
use Lunar\Analytics\Filament\Concerns\HasPeriodFilters;

class ConversionsPage extends Dashboard
{
    use HasPeriodFilters;

    /**
     * Staff permission that opens the report.
     */
    public const PERMISSION = 'analytics:conversions';

    protected static string $routePath = 'analytics/conversions';

    protected static ?string $slug = 'analytics/conversions';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    /**
     * Whether the current staff user may open the report. Admins pass through Lunar's `Gate::after`.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows(self::PERMISSION);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.analytics::plugin.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.analytics::conversions.page.navigation_label');
    }

    public function getTitle(): string
    {
        return __('lunarpanel.analytics::conversions.page.title');
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return static::conversionWidgets();
    }

    /**
     * The report widgets, in display order.
     *
     * @return list<class-string>
     */
    public static function conversionWidgets(): array
    {
        return [
            ConversionStatsOverview::class,
            ConversionsOverTimeChart::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 2;
    }
}
