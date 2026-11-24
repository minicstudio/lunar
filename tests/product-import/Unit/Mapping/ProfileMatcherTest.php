<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\ProductImport\Mapping\HeaderNormalizer;
use Lunar\ProductImport\Mapping\ProfileMatcher;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Twelve supplier headers.
 *
 * @return list<string>
 */
function twelveHeaders(): array
{
    return ['Cod', 'Cod parinte', 'Denumire', 'Descriere', 'Pret', 'Stoc', 'Marime', 'Culoare', 'Brand', 'Categorie', 'EAN', 'Greutate'];
}

function profileWith(array $headers, string $name = 'Furnizor Textil SRL export'): ProductImportProfile
{
    $matcher = app(ProfileMatcher::class);

    return ProductImportProfile::factory()->create([
        'name' => $name,
        'headers' => (new HeaderNormalizer)->normalizeAll($headers),
        'header_fingerprint' => $matcher->fingerprint($headers),
    ]);
}

test('reordered headers in another case give a full match', function () {
    $profile = profileWith(twelveHeaders());

    $headers = array_reverse(array_map(fn (string $header) => mb_strtoupper($header).' ', twelveHeaders()));
    $match = app(ProfileMatcher::class)->best($headers);

    expect($match->profile->is($profile))->toBeTrue()
        ->and($match->score)->toBe(1.0)
        ->and(app(ProfileMatcher::class)->fingerprint($headers))->toBe($profile->header_fingerprint);
});

test('accents are ignored', function () {
    expect(app(ProfileMatcher::class)->score(['Mărime', 'Preț'], ['marime', 'PRET']))->toBe(1.0);
});

test('11 shared headers of 12 and 12 give a 92% match', function () {
    profileWith(twelveHeaders());

    $headers = twelveHeaders();
    $headers[11] = 'Sezon';

    $match = app(ProfileMatcher::class)->best($headers);

    expect(round($match->score * 100))->toBe(92.0);
});

test('below 80% gives no match', function () {
    profileWith(twelveHeaders());

    $headers = array_slice(twelveHeaders(), 0, 9);
    array_push($headers, 'Sezon', 'Colectie', 'Material');

    expect(app(ProfileMatcher::class)->best($headers))->toBeNull()
        ->and(app(ProfileMatcher::class)->score($headers, twelveHeaders()))->toBe(0.75);
});

test('the best match comes first', function () {
    profileWith(array_slice(twelveHeaders(), 0, 11), 'Partial');
    $full = profileWith(twelveHeaders(), 'Full');

    $matches = app(ProfileMatcher::class)->matches(twelveHeaders());

    expect($matches)->toHaveCount(2)
        ->and($matches[0]->profile->is($full))->toBeTrue();
});
