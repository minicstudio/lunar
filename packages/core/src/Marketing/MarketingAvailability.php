<?php

namespace Lunar\Marketing;

final class MarketingAvailability
{
    /**
     * Whether any marketing package can accept newsletter / explicit opt-in subscription.
     * Reads only lunar.* provider package configs — never lunar-frontend.*.
     */
    public function newsletterSubscriptionAvailable(): bool
    {
        return (bool) config('lunar.mailchimp.enabled', false)
            || (bool) config('lunar.klaviyo.enabled', false);
    }
}
