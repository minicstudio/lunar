<?php

namespace Lunar\Analytics\Traffic\Support;

use Illuminate\Support\Str;

/**
 * Names a traffic source in words, for the order badge and the report.
 */
class TrafficSourceLabel
{
    /**
     * Brand spelling of the default sources.
     */
    public const BRAND_NAMES = [
        'google' => 'Google',
        'bing' => 'Bing',
        'yahoo' => 'Yahoo',
        'duckduckgo' => 'DuckDuckGo',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
    ];

    /**
     * Badge color per medium; any other medium is gray.
     */
    public const MEDIUM_COLORS = [
        'organic' => 'success',
        'cpc' => 'warning',
        'social' => 'info',
        'email' => 'primary',
    ];

    /**
     * Sources named through `traffic.sources.<source>`.
     */
    protected const TRANSLATED_SOURCES = ['direct'];

    /**
     * Mediums named through `traffic.mediums.<medium>`.
     */
    protected const TRANSLATED_MEDIUMS = ['organic', 'cpc', 'social', 'referral', 'email'];

    /**
     * Medium left out of the label.
     */
    protected const NO_MEDIUM = 'none';

    protected function __construct(
        protected ?string $source,
        protected ?string $medium,
        protected ?string $campaign,
    ) {}

    /**
     * Names an order's or cart's `meta.traffic_source`.
     *
     * @param  mixed  $trafficSource  An `array{visit_id?: int, source?: string, medium?: ?string, campaign?: ?string}`; anything else is unknown.
     */
    public static function for(mixed $trafficSource): self
    {
        $value = function (string $key) use ($trafficSource): ?string {
            $value = is_array($trafficSource) ? ($trafficSource[$key] ?? null) : null;

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };

        return new self($value('source'), $value('medium'), $value('campaign'));
    }

    /**
     * The name of a source: "Direct", a brand name, a host as stored, or the headlined source.
     */
    public static function nameOfSource(string $source): string
    {
        if (in_array($source, self::TRANSLATED_SOURCES, true)) {
            return __('lunarpanel.analytics::traffic.sources.'.$source);
        }

        if (isset(self::BRAND_NAMES[$source])) {
            return self::BRAND_NAMES[$source];
        }

        return str_contains($source, '.') ? $source : Str::headline($source);
    }

    /**
     * The name of a medium, or null for `none` and an empty medium.
     */
    public static function nameOfMedium(?string $medium): ?string
    {
        if ($medium === null || trim($medium) === '' || $medium === self::NO_MEDIUM) {
            return null;
        }

        if (in_array($medium, self::TRANSLATED_MEDIUMS, true)) {
            return __('lunarpanel.analytics::traffic.mediums.'.$medium);
        }

        return Str::headline($medium);
    }

    /**
     * The source name, or "Unknown" without a source.
     */
    public function sourceName(): string
    {
        return $this->source === null
            ? __('lunarpanel.analytics::traffic.unknown')
            : self::nameOfSource($this->source);
    }

    /**
     * The medium name, or null without a source or a named medium.
     */
    public function mediumName(): ?string
    {
        return $this->source === null ? null : self::nameOfMedium($this->medium);
    }

    /**
     * The badge label, such as "Google · Paid ad", "Direct" or "Unknown".
     */
    public function label(): string
    {
        return collect([$this->sourceName(), $this->mediumName()])->filter()->implode(' · ');
    }

    /**
     * The badge color, by medium.
     */
    public function color(): string
    {
        if ($this->source === null || $this->medium === null) {
            return 'gray';
        }

        return self::MEDIUM_COLORS[$this->medium] ?? 'gray';
    }

    /**
     * The campaign, or null without one or without a source.
     */
    public function campaign(): ?string
    {
        return $this->source === null ? null : $this->campaign;
    }
}
