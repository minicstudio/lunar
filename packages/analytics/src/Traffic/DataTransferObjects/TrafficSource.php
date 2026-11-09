<?php

namespace Lunar\Analytics\Traffic\DataTransferObjects;

/**
 * The classified source of a storefront request.
 */
readonly class TrafficSource
{
    /**
     * @param  string  $source  Trimmed, lowercased source (e.g. `google`, `arukereso.hu`, `direct`).
     * @param  ?string  $medium  Trimmed, lowercased medium (e.g. `organic`, `cpc`, `referral`, `none`).
     * @param  ?string  $campaign  Trimmed `utm_campaign`, case kept.
     * @param  ?string  $referrerHost  External referrer host without a leading `www.`.
     */
    public function __construct(
        public string $source,
        public ?string $medium = null,
        public ?string $campaign = null,
        public ?string $referrerHost = null,
    ) {}
}
