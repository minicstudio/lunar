<?php

namespace Lunar\Marketing;

final class MarketingAvailability
{
    /**
     * Whether any marketing package can accept newsletter / explicit opt-in subscription.
     * Reads only lunar.* provider package configs — never lunar-frontend.*.
     * A provider counts only when enabled AND required subscribe credentials exist
     * (avoids UI that queues consent jobs that fail for missing api_key/list_id).
     */
    public function newsletterSubscriptionAvailable(): bool
    {
        return $this->mailchimpNewsletterCapable()
            || $this->klaviyoNewsletterCapable();
    }

    private function mailchimpNewsletterCapable(): bool
    {
        return (bool) config('lunar.mailchimp.enabled', false)
            && filled(config('lunar.mailchimp.api_key'))
            && filled(config('lunar.mailchimp.list_id'));
    }

    private function klaviyoNewsletterCapable(): bool
    {
        // Explicit opt-in UI needs the DOI list; automatic_list_id alone is not enough
        // for footer/checkbox newsletter forms.
        return (bool) config('lunar.klaviyo.enabled', false)
            && filled(config('lunar.klaviyo.api_key'))
            && filled(config('lunar.klaviyo.list_id'));
    }
}
