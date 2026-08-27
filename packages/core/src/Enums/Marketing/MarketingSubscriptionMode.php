<?php

namespace Lunar\Enums\Marketing;

enum MarketingSubscriptionMode: string
{
    /**
     * Known-customer / shop-policy subscription path (verified account, or
     * automatic subscription after order when shop policy authorizes it).
     * Provider must subscribe immediately with marketing consent granted —
     * no confirmation / double-opt-in email (Mailchimp: status_if_new=subscribed;
     * Klaviyo: Bulk Subscribe to a single opt-in list — never historical_import).
     * Does NOT by itself prove the human ticked a consent checkbox —
     * the host must only emit ConsentGranted when shop policy + product
     * rules authorize subscription processing for this registration or order.
     */
    case CustomerRegistration = 'customer_registration';

    /**
     * Explicit opt-in path (newsletter footer form, registration checkbox,
     * checkout newsletter checkbox, etc.).
     * Provider must use double-opt-in / pending semantics so the person
     * receives a confirmation email and is not fully subscribed until confirmed
     * (Mailchimp: pending; Klaviyo: Bulk Subscribe to a double opt-in list,
     * without historical_import).
     */
    case ExplicitOptIn = 'explicit_opt_in';
}
