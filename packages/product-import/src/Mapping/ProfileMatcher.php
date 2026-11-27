<?php

namespace Lunar\ProductImport\Mapping;

use Lunar\ProductImport\Models\ProductImportProfile;

/**
 * Finds saved profiles whose headers match a sheet's headers, ignoring case,
 * accents, surrounding spaces, and column order. The match share is the
 * number of shared headers divided by the larger header count.
 */
class ProfileMatcher
{
    public function __construct(protected HeaderNormalizer $normalizer) {}

    /**
     * The profiles that reach the configured share, best match first.
     *
     * @param  iterable<?string>  $headers
     * @return list<ProfileMatch>
     */
    public function matches(iterable $headers): array
    {
        $normalized = $this->normalizer->normalizeAll($headers);
        $threshold = (float) config('lunar.product-import.profiles.match_threshold', 0.80);

        if ($normalized === []) {
            return [];
        }

        $matches = [];

        foreach (ProductImportProfile::query()->orderByDesc('last_used_at')->orderByDesc('id')->get() as $profile) {
            $score = $this->score($normalized, $profile->headers ?? []);

            if ($score >= $threshold) {
                $matches[] = new ProfileMatch($profile, $score);
            }
        }

        usort($matches, fn (ProfileMatch $a, ProfileMatch $b): int => $b->score <=> $a->score);

        return $matches;
    }

    /**
     * The best matching profile, if any reaches the configured share.
     *
     * @param  iterable<?string>  $headers
     */
    public function best(iterable $headers): ?ProfileMatch
    {
        return $this->matches($headers)[0] ?? null;
    }

    /**
     * The match share of two header lists.
     *
     * @param  array<?string>  $headers
     * @param  array<?string>  $otherHeaders
     */
    public function score(array $headers, array $otherHeaders): float
    {
        $a = $this->normalizer->normalizeAll($headers);
        $b = $this->normalizer->normalizeAll($otherHeaders);

        $larger = max(count($a), count($b));

        if ($larger === 0) {
            return 0.0;
        }

        return round(count(array_intersect($a, $b)) / $larger, 4);
    }

    /**
     * The fingerprint of a header list: a hash of its sorted comparison forms.
     *
     * @param  iterable<?string>  $headers
     */
    public function fingerprint(iterable $headers): string
    {
        $normalized = $this->normalizer->normalizeAll($headers);
        sort($normalized);

        return hash('sha256', implode("\n", $normalized));
    }
}
