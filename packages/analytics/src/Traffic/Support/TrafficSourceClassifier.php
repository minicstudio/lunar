<?php

namespace Lunar\Analytics\Traffic\Support;

use Illuminate\Http\Request;
use Lunar\Analytics\Traffic\Contracts\ClassifiesTrafficSource;
use Lunar\Analytics\Traffic\DataTransferObjects\TrafficSource;

/**
 * Default traffic source rules, configured in `lunar.analytics.traffic`.
 *
 * Precedence: `utm_source`; click ids marked `before_referrer`; known
 * referrer hosts; the other click ids; any other external host (host /
 * referral); direct / none.
 */
class TrafficSourceClassifier implements ClassifiesTrafficSource
{
    /**
     * Longest value stored for any field.
     */
    protected const MAX_LENGTH = 255;

    /**
     * {@inheritDoc}
     */
    public function classify(Request $request): ?TrafficSource
    {
        $referrerHost = $this->referrerHost($request);
        $isOwnHost = $referrerHost !== null && $referrerHost === $this->withoutWww(strtolower($request->getHost()));

        if ($isOwnHost && ! $this->hasCampaignParameters($request)) {
            return null;
        }

        $externalHost = $isOwnHost ? null : $referrerHost;

        return $this->fromUtmParameters($request, $externalHost)
            ?? $this->fromClickIds($request, $externalHost, beforeReferrer: true)
            ?? $this->fromReferrerHosts($externalHost)
            ?? $this->fromClickIds($request, $externalHost, beforeReferrer: false)
            ?? $this->fromExternalHost($externalHost)
            ?? $this->make('direct', 'none');
    }

    /**
     * {@inheritDoc}
     */
    public function hasCampaignParameters(Request $request): bool
    {
        if ($this->queryValue($request, 'utm_source') !== null) {
            return true;
        }

        foreach (array_keys($this->clickIds()) as $parameter) {
            if ($this->queryValue($request, (string) $parameter) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Source from `utm_source`, with `utm_medium` and `utm_campaign`.
     */
    protected function fromUtmParameters(Request $request, ?string $referrerHost): ?TrafficSource
    {
        $source = $this->queryValue($request, 'utm_source');

        if ($source === null) {
            return null;
        }

        return $this->make(
            $source,
            $this->queryValue($request, 'utm_medium'),
            $this->queryValue($request, 'utm_campaign'),
            $referrerHost,
        );
    }

    /**
     * Source from the first configured click id present on the request.
     *
     * @param  bool  $beforeReferrer  Only consider click ids whose `before_referrer` flag has this value.
     */
    protected function fromClickIds(Request $request, ?string $referrerHost, bool $beforeReferrer): ?TrafficSource
    {
        foreach ($this->clickIds() as $parameter => $definition) {
            if ((bool) ($definition['before_referrer'] ?? false) !== $beforeReferrer) {
                continue;
            }

            if ($this->queryValue($request, (string) $parameter) === null) {
                continue;
            }

            if ($source = $this->fromDefinition($definition, $referrerHost)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Source from the first configured referrer host entry matching the host.
     */
    protected function fromReferrerHosts(?string $referrerHost): ?TrafficSource
    {
        if ($referrerHost === null) {
            return null;
        }

        foreach ($this->referrerHosts() as $pattern => $definition) {
            if (! $this->hostMatches($referrerHost, (string) $pattern)) {
                continue;
            }

            if ($source = $this->fromDefinition($definition, $referrerHost)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Any other external host gives that host as source with medium `referral`.
     */
    protected function fromExternalHost(?string $referrerHost): ?TrafficSource
    {
        if ($referrerHost === null) {
            return null;
        }

        return $this->make($referrerHost, 'referral', null, $referrerHost);
    }

    /**
     * Build a source from a configured `['source' => ..., 'medium' => ...]` entry.
     *
     * @param  mixed  $definition  The config entry.
     */
    protected function fromDefinition(mixed $definition, ?string $referrerHost): ?TrafficSource
    {
        if (! is_array($definition) || ! is_string($definition['source'] ?? null) || trim($definition['source']) === '') {
            return null;
        }

        $medium = $definition['medium'] ?? null;

        return $this->make(
            $definition['source'],
            is_string($medium) ? $medium : null,
            null,
            $referrerHost,
        );
    }

    /**
     * Whether a host matches a configured entry.
     *
     * An entry matches that host and its subdomains. A trailing `.*` matches
     * any one- or two-label suffix (`google.*` matches `google.com` and
     * `google.co.uk`).
     */
    protected function hostMatches(string $host, string $pattern): bool
    {
        $pattern = $this->withoutWww(strtolower(trim($pattern)));

        if ($pattern === '') {
            return false;
        }

        if (str_ends_with($pattern, '.*')) {
            $base = preg_quote(substr($pattern, 0, -2), '/');

            return (bool) preg_match('/(^|\.)'.$base.'\.[^.]+(\.[^.]+)?$/', $host);
        }

        return $host === $pattern || str_ends_with($host, '.'.$pattern);
    }

    /**
     * The referrer's host, lowercased and without a leading `www.`.
     *
     * Returns null when there is no referrer or it cannot be parsed.
     */
    protected function referrerHost(Request $request): ?string
    {
        $referrer = trim((string) $request->headers->get('referer', ''));

        if ($referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || trim($host, '. ') === '') {
            return null;
        }

        return $this->withoutWww(strtolower(trim($host, '. ')));
    }

    /**
     * A trimmed, non-empty string query parameter, or null.
     */
    protected function queryValue(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalise and build the source: values trimmed, source and medium
     * lowercased, every value cut to 255 characters.
     */
    protected function make(string $source, ?string $medium = null, ?string $campaign = null, ?string $referrerHost = null): TrafficSource
    {
        return new TrafficSource(
            source: $this->cut(mb_strtolower(trim($source))),
            medium: $this->nullableValue($medium, lowercase: true),
            campaign: $this->nullableValue($campaign, lowercase: false),
            referrerHost: $this->nullableValue($referrerHost, lowercase: true),
        );
    }

    /**
     * Trim, optionally lowercase and cut a value; empty values become null.
     */
    protected function nullableValue(?string $value, bool $lowercase): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $this->cut($lowercase ? mb_strtolower($value) : $value);
    }

    /**
     * Cut a value to the longest stored length.
     */
    protected function cut(string $value): string
    {
        return mb_substr($value, 0, static::MAX_LENGTH);
    }

    /**
     * Strip a leading `www.` from a host.
     */
    protected function withoutWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Configured referrer host map.
     *
     * @return array<string, array{source: string, medium?: ?string}>
     */
    protected function referrerHosts(): array
    {
        return (array) config('lunar.analytics.traffic.referrer_hosts', []);
    }

    /**
     * Configured click-id map.
     *
     * @return array<string, array{source: string, medium?: ?string, before_referrer?: bool}>
     */
    protected function clickIds(): array
    {
        return (array) config('lunar.analytics.traffic.click_ids', []);
    }
}
