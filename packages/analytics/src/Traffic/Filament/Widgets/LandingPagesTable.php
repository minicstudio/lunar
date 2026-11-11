<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;

/**
 * The period's landing paths, most visited first. Not linked to carts or orders.
 */
class LandingPagesTable extends TableWidget
{
    use InteractsWithTrafficReport;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('lunarpanel.analytics::traffic.widgets.landing_pages.heading'))
            ->records(fn (int $page, int|string $recordsPerPage): LengthAwarePaginator => $this->trafficReport()->paginateLandingPages(
                perPage: (int) $recordsPerPage,
                page: $page,
            ))
            ->columns([
                TextColumn::make('landing_path')
                    ->label(__('lunarpanel.analytics::traffic.columns.landing_path'))
                    ->wrap(),
                TextColumn::make('visits')
                    ->label(__('lunarpanel.analytics::traffic.columns.visits'))
                    ->numeric()
                    ->alignEnd(),
            ])
            ->emptyStateHeading(__('lunarpanel.analytics::traffic.no_data'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
