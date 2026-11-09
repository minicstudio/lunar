<?php

namespace Lunar\Analytics\Traffic\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Lunar\Analytics\Traffic\Filament\Widgets\LandingPagesTable;
use Lunar\Analytics\Traffic\Filament\Widgets\TrafficSourceFunnelTable;
use Lunar\Analytics\Traffic\Filament\Widgets\TrafficStatsOverview;
use Lunar\Analytics\Traffic\Filament\Widgets\VisitsBySourceChart;
use Lunar\Analytics\Traffic\Filament\Widgets\VisitsOverTimeChart;
use Throwable;

/**
 * Traffic sources report: visits, carts and orders by source for a period.
 *
 * Open to admins and to staff holding `analytics:traffic-sources`.
 */
class TrafficSourcesPage extends Dashboard
{
    use HasFiltersForm;

    /**
     * Staff permission that opens the report.
     */
    public const PERMISSION = 'analytics:traffic-sources';

    /**
     * Days covered by the default period, today included.
     */
    public const DEFAULT_PERIOD_DAYS = 30;

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
     * Period filters: start and end dates, both included.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    DatePicker::make('from')
                        ->label(__('lunarpanel.analytics::traffic.filters.from'))
                        ->default(fn (): string => static::defaultFrom()->toDateString())
                        ->maxDate(fn (Get $get): string => $get('to') ?: now()->toDateString())
                        ->required(),
                    DatePicker::make('to')
                        ->label(__('lunarpanel.analytics::traffic.filters.to'))
                        ->default(fn (): string => now()->toDateString())
                        ->minDate(fn (Get $get): ?string => $get('from') ?: null)
                        ->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
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

    /**
     * First day of the default period: today minus 29 days.
     */
    public static function defaultFrom(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(static::DEFAULT_PERIOD_DAYS - 1)->startOfDay();
    }

    /**
     * The period chosen in the page filters, falling back to the default period.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function periodFromFilters(?array $filters): array
    {
        return [
            static::parseDate($filters['from'] ?? null) ?? static::defaultFrom(),
            static::parseDate($filters['to'] ?? null) ?? CarbonImmutable::now()->startOfDay(),
        ];
    }

    /**
     * Parse a filter date, or null when it is missing or invalid.
     */
    protected static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
