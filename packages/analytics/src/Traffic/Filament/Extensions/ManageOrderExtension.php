<?php

namespace Lunar\Analytics\Traffic\Filament\Extensions;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Support\TrafficSourceLabel;

/**
 * Shows the order's traffic source in its own "Traffic source" section,
 * directly above "Additional info", for staff who may open the traffic
 * sources report, and hides the raw key from everyone.
 *
 * The section shows the badge and each detail that has a value: the
 * campaign from the order, and the referrer host, landing page and start
 * time of the order's visit. The visit is read once per request.
 */
class ManageOrderExtension extends ViewPageExtension
{
    /**
     * Visits read for this request, by visit id; `null` when the row is missing.
     *
     * @var array<int, ?TrafficVisit>
     */
    protected array $visits = [];

    /**
     * Put the "Traffic source" section above the given "Additional info" section.
     */
    public function extendAdditionalInfoSection(Component $section): Component
    {
        return Group::make([
            $this->trafficSourceSection(),
            $section,
        ]);
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

    /**
     * The badge, the campaign and the visit's referrer, landing page and start.
     * Every entry but the badge shows only when it has a value.
     */
    protected function trafficSourceSection(): Section
    {
        return Section::make('traffic_source')
            ->key('traffic_source')
            ->heading(__('lunarpanel.analytics::traffic.order.traffic_source'))
            ->compact()
            ->visible(fn (): bool => TrafficSourcesPage::canAccess())
            ->schema([
                TextEntry::make('traffic_source_badge')
                    ->label(__('lunarpanel.analytics::traffic.order.source'))
                    ->state(fn (?Model $record): string => $this->trafficSourceOf($record)->label())
                    ->color(fn (?Model $record): string => $this->trafficSourceOf($record)->color())
                    ->badge(),
                TextEntry::make('traffic_source_campaign')
                    ->label(__('lunarpanel.analytics::traffic.order.campaign'))
                    ->state(fn (?Model $record): ?string => $this->trafficSourceOf($record)->campaign())
                    ->visible(fn (?Model $record): bool => filled($this->trafficSourceOf($record)->campaign())),
                TextEntry::make('traffic_source_referrer')
                    ->label(__('lunarpanel.analytics::traffic.order.referrer'))
                    ->state(fn (?Model $record): ?string => $this->visitOf($record)?->referrer_host)
                    ->visible(fn (?Model $record): bool => filled($this->visitOf($record)?->referrer_host)),
                TextEntry::make('traffic_source_landing_page')
                    ->label(__('lunarpanel.analytics::traffic.order.landing_page'))
                    ->state(fn (?Model $record): ?string => $this->visitOf($record)?->landing_path)
                    ->url(fn (?Model $record): ?string => filled($landingPath = $this->visitOf($record)?->landing_path)
                        ? url($landingPath)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (?Model $record): bool => filled($this->visitOf($record)?->landing_path)),
                TextEntry::make('traffic_source_visited_at')
                    ->label(__('lunarpanel.analytics::traffic.order.visited_at'))
                    ->state(fn (?Model $record): ?Carbon => $this->visitOf($record)?->created_at)
                    ->dateTime('Y-m-d h:i a', timezone: config('lunar.panel.timezone'))
                    ->visible(fn (?Model $record): bool => filled($this->visitOf($record)?->created_at)),
            ]);
    }

    protected function trafficSourceOf(?Model $order): TrafficSourceLabel
    {
        return TrafficSourceLabel::for($this->trafficSourceMeta($order));
    }

    /**
     * The order's visit, read with one query per visit id and request.
     * `null` without a `traffic_source.visit_id` or when the row is missing.
     */
    protected function visitOf(?Model $order): ?TrafficVisit
    {
        $visitId = data_get($this->trafficSourceMeta($order), 'visit_id');

        if (! is_numeric($visitId)) {
            return null;
        }

        $visitId = (int) $visitId;

        if (! array_key_exists($visitId, $this->visits)) {
            $this->visits[$visitId] = TrafficVisit::find($visitId);
        }

        return $this->visits[$visitId];
    }

    protected function trafficSourceMeta(?Model $order): mixed
    {
        return data_get($order?->getAttribute('meta'), 'traffic_source');
    }
}
