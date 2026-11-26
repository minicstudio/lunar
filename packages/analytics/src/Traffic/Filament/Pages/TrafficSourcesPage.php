<?php

namespace Lunar\Analytics\Traffic\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Lunar\Analytics\Filament\Concerns\HasPeriodFilters;
use Lunar\Analytics\Traffic\Filament\Widgets\LandingPagesTable;
use Lunar\Analytics\Traffic\Filament\Widgets\TrafficSourceFunnelTable;
use Lunar\Analytics\Traffic\Filament\Widgets\TrafficStatsOverview;
use Lunar\Analytics\Traffic\Filament\Widgets\VisitsBySourceChart;
use Lunar\Analytics\Traffic\Filament\Widgets\VisitsOverTimeChart;

/**
 * Traffic sources report: visits, carts and orders by source for a period.
 *
 * Open to admins and to staff holding `analytics:traffic-sources`.
 */
class TrafficSourcesPage extends Dashboard
{
    use HasPeriodFilters;

    /**
     * Staff permission that opens the report.
     */
    public const PERMISSION = 'analytics:traffic-sources';

    protected static string $routePath = 'analytics/traffic-sources';

    protected static ?string $slug = 'analytics/traffic-sources';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

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
        return __('lunarpanel.analytics::traffic.page.navigation_label');
    }

    public function getTitle(): string
    {
        return __('lunarpanel.analytics::traffic.page.title');
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return static::trafficWidgets();
    }

    /**
     * The report widgets, in display order.
     *
     * @return list<class-string>
     */
    public static function trafficWidgets(): array
    {
        return [
            TrafficStatsOverview::class,
            VisitsBySourceChart::class,
            VisitsOverTimeChart::class,
            TrafficSourceFunnelTable::class,
            LandingPagesTable::class,
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
