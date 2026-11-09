<?php

namespace Lunar\Analytics\Traffic\Filament\Extensions;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage;
use Lunar\Analytics\Traffic\Support\TrafficSourceLabel;

/**
 * Shows the order's traffic source in the order summary, for staff who may
 * open the traffic sources report, and hides the raw key from everyone.
 */
class ManageOrderExtension extends ViewPageExtension
{
    /**
     * Append the traffic source badge, and the campaign when there is one.
     *
     * @param  array<int, Component>  $schema
     * @return array<int, Component>
     */
    public function extendOrderSummarySchema(array $schema): array
    {
        return [
            ...$schema,
            TextEntry::make('traffic_source')
                ->label(__('lunarpanel.analytics::traffic.order.traffic_source'))
                ->state(fn (?Model $record): string => $this->trafficSourceOf($record)->label())
                ->color(fn (?Model $record): string => $this->trafficSourceOf($record)->color())
                ->badge()
                ->alignEnd()
                ->visible(fn (): bool => TrafficSourcesPage::canAccess()),
            TextEntry::make('traffic_source_campaign')
                ->label(__('lunarpanel.analytics::traffic.order.campaign'))
                ->state(fn (?Model $record): ?string => $this->trafficSourceOf($record)->campaign())
                ->alignEnd()
                ->visible(fn (?Model $record): bool => TrafficSourcesPage::canAccess()
                    && filled($this->trafficSourceOf($record)->campaign())),
        ];
    }

    /**
     * Hide the raw `traffic_source` from "Additional info" for every staff member.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public function extendHiddenOrderMetaKeys(array $keys): array
    {
        return [
            ...$keys,
            'traffic_source',
        ];
    }

    protected function trafficSourceOf(?Model $order): TrafficSourceLabel
    {
        return TrafficSourceLabel::for(data_get($order?->getAttribute('meta'), 'traffic_source'));
    }
}
