<?php

namespace Lunar\Analytics\Filament\Concerns;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

trait HasPeriodFilters
{
    use HasFiltersForm;

    /**
     * Days covered by the default period, today included.
     */
    public const DEFAULT_PERIOD_DAYS = 30;

    /**
     * Period filters: start and end dates, both included.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    DatePicker::make('from')
                        ->label(__('lunarpanel.analytics::plugin.filters.from'))
                        ->default(fn (): string => static::defaultFrom()->toDateString())
                        ->maxDate(fn (Get $get): string => $get('to') ?: now()->toDateString())
                        ->required(),
                    DatePicker::make('to')
                        ->label(__('lunarpanel.analytics::plugin.filters.to'))
                        ->default(fn (): string => now()->toDateString())
                        ->minDate(fn (Get $get): ?string => $get('from') ?: null)
                        ->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
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
