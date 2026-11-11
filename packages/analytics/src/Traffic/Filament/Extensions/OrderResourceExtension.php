<?php

namespace Lunar\Analytics\Traffic\Filament\Extensions;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage;
use Lunar\Analytics\Traffic\Support\TrafficSourceLabel;

/**
 * Adds the traffic source badge to the admin order list, for staff who may
 * open the traffic sources report.
 */
class OrderResourceExtension extends ResourceExtension
{
    /**
     * Insert the toggleable traffic source column after the customer type column.
     */
    public function extendTable(Table $table): Table
    {
        $columns = [];

        foreach ($table->getColumns() as $name => $column) {
            $columns[] = $column;

            if ($name === 'new_customer') {
                $columns[] = $this->trafficSourceColumn();
            }
        }

        return $table->columns($columns);
    }

    /**
     * The badge reads the order's `meta.traffic_source`, so it adds no query.
     * It shows the campaign as tooltip, and is not sortable or searchable.
     */
    protected function trafficSourceColumn(): TextColumn
    {
        return TextColumn::make('traffic_source')
            ->label(__('lunarpanel.analytics::traffic.order.traffic_source'))
            ->state(fn (Model $record): string => $this->trafficSourceOf($record)->label())
            ->color(fn (Model $record): string => $this->trafficSourceOf($record)->color())
            ->tooltip(fn (Model $record): ?string => $this->trafficSourceOf($record)->campaign())
            ->badge()
            ->toggleable()
            ->visible(fn (): bool => TrafficSourcesPage::canAccess());
    }

    protected function trafficSourceOf(Model $order): TrafficSourceLabel
    {
        return TrafficSourceLabel::for(data_get($order->getAttribute('meta'), 'traffic_source'));
    }
}
