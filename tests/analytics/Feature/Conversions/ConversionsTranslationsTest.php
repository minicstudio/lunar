<?php

use Illuminate\Support\Arr;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);

/**
 * The dotted keys of a locale's `conversions.php`.
 *
 * @return list<string>
 */
function conversionTranslationKeys(string $locale): array
{
    return array_keys(Arr::dot(require dirname(__DIR__, 4)."/packages/analytics/resources/lang/{$locale}/conversions.php"));
}

test('every conversions translation key resolves in each admin locale', function (string $locale) {
    $keys = conversionTranslationKeys('en');

    expect(conversionTranslationKeys($locale))->toEqualCanonicalizing($keys);

    foreach ($keys as $key) {
        expect(trans("lunarpanel.analytics::conversions.{$key}", [], $locale))
            ->not->toBe("lunarpanel.analytics::conversions.{$key}");
    }
})->with(['en', 'hu', 'ro']);

test('the filter labels resolve from the plugin translations in each admin locale', function (string $locale) {
    foreach (['from', 'to'] as $key) {
        expect(trans("lunarpanel.analytics::plugin.filters.{$key}", [], $locale))
            ->not->toBe("lunarpanel.analytics::plugin.filters.{$key}");
    }
})->with(['en', 'hu', 'ro']);
