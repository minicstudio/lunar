<?php

use Lunar\Analytics\Traffic\Support\TrafficSourceClassifier;

return [
    /*
    |--------------------------------------------------------------------------
    | Traffic sources
    |--------------------------------------------------------------------------
    |
    | Records where storefront visitors come from, stamps new carts with the
    | source of the current visit and powers the admin "Traffic sources"
    | report. Hosts attach the `RecordTrafficVisit` middleware to their
    | storefront routes, inside `StartSession`.
    |
    */
    'traffic' => [

        /*
        |----------------------------------------------------------------------
        | Enabled
        |----------------------------------------------------------------------
        |
        | When false, no visits are recorded and new carts get no traffic
        | source. The report keeps showing the data already stored.
        |
        */
        'enabled' => env('ANALYTICS_TRAFFIC_ENABLED', false),

        /*
        |----------------------------------------------------------------------
        | Classifier
        |----------------------------------------------------------------------
        |
        | Class implementing `ClassifiesTrafficSource`. Replace it to change
        | how a request's source, medium and campaign are worked out.
        |
        */
        'classifier' => TrafficSourceClassifier::class,

        /*
        |----------------------------------------------------------------------
        | Referrer hosts
        |----------------------------------------------------------------------
        |
        | Known referrer hosts and the source / medium they give. An entry
        | matches that host and its subdomains. A trailing `.*` matches any
        | one- or two-label suffix (`google.com`, `google.co.uk`). The first
        | matching entry wins. Any other external host gives that host as
        | source with medium `referral`.
        |
        */
        'referrer_hosts' => [
            'google.*' => ['source' => 'google', 'medium' => 'organic'],
            'bing.com' => ['source' => 'bing', 'medium' => 'organic'],
            'yahoo.com' => ['source' => 'yahoo', 'medium' => 'organic'],
            'duckduckgo.com' => ['source' => 'duckduckgo', 'medium' => 'organic'],
            'facebook.com' => ['source' => 'facebook', 'medium' => 'social'],
            'fb.com' => ['source' => 'facebook', 'medium' => 'social'],
            'fb.me' => ['source' => 'facebook', 'medium' => 'social'],
            'instagram.com' => ['source' => 'instagram', 'medium' => 'social'],
            'tiktok.com' => ['source' => 'tiktok', 'medium' => 'social'],
            'youtube.com' => ['source' => 'youtube', 'medium' => 'social'],
            'youtu.be' => ['source' => 'youtube', 'medium' => 'social'],
        ],

        /*
        |----------------------------------------------------------------------
        | Click ids
        |----------------------------------------------------------------------
        |
        | Ad click query parameters and the source / medium they give when no
        | `utm_source` is present. With `before_referrer` true the click id
        | wins over a known referrer host; otherwise it only decides when the
        | referrer is not a known host. Every click id listed here counts as a
        | campaign parameter, so it can start a new visit mid-session.
        |
        */
        'click_ids' => [
            'gclid' => ['source' => 'google', 'medium' => 'cpc', 'before_referrer' => true],
            'gbraid' => ['source' => 'google', 'medium' => 'cpc', 'before_referrer' => true],
            'wbraid' => ['source' => 'google', 'medium' => 'cpc', 'before_referrer' => true],
            'fbclid' => ['source' => 'facebook', 'medium' => 'social', 'before_referrer' => false],
        ],

        /*
        |----------------------------------------------------------------------
        | Bot user agent pattern
        |----------------------------------------------------------------------
        |
        | Requests whose user agent matches this regular expression, or is
        | empty, are never recorded. Extend it with your own monitoring tools.
        | `(?<!cu)bot` keeps Cubot phones, whose in-app browsers send the model.
        |
        */
        'bot_user_agent_pattern' => '/(?<!cu)bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python-requests|go-http-client|java\//i',
    ],
];
