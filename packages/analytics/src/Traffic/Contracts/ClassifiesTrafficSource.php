<?php

namespace Lunar\Analytics\Traffic\Contracts;

use Illuminate\Http\Request;
use Lunar\Analytics\Traffic\DataTransferObjects\TrafficSource;

/**
 * Works out where a storefront request comes from.
 *
 * Bound to `lunar.analytics.traffic.classifier`, so a shop can replace the
 * default rules with its own class.
 */
interface ClassifiesTrafficSource
{
    /**
     * Classify the request's source, medium and campaign.
     *
     * Returns null when the request is navigation from the shop itself
     * (same-host referrer) without campaign parameters, so it starts no visit.
     */
    public function classify(Request $request): ?TrafficSource;

    /**
     * Whether the request carries campaign parameters (`utm_source` or a
     * configured click id), which can start a new visit mid-session.
     */
    public function hasCampaignParameters(Request $request): bool;
}
