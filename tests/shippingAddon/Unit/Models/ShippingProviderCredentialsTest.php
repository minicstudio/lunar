<?php

uses(\Lunar\Tests\shippingAddon\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Carbon\Carbon;
use Lunar\Addons\Shipping\Models\ShippingProviderCredentials;

test('for returns null when provider does not exist', function () {
    expect(ShippingProviderCredentials::for('sameday'))->toBeNull();
});

test('for returns the record for the given provider', function () {
    $expiresAt = Carbon::now('UTC')->addDay();

    $created = ShippingProviderCredentials::create([
        'provider' => 'sameday',
        'token' => 'tok_sameday',
        'expires_at' => $expiresAt,
    ]);

    $found = ShippingProviderCredentials::for('sameday');

    expect($found)
        ->not->toBeNull()
        ->and($found->id)->toBe($created->id)
        ->and($found->token)->toBe('tok_sameday')
        ->and($found->expires_at->toDateTimeString())
        ->toBe($expiresAt->toDateTimeString());
});

test('validTokenFor returns token when it expires in more than 3 hours', function () {
    ShippingProviderCredentials::create([
        'provider' => 'dpd',
        'token' => 'tok_dpd_valid',
        'expires_at' => Carbon::now('UTC')->addHours(4),
    ]);

    expect(ShippingProviderCredentials::validTokenFor('dpd'))->toBe('tok_dpd_valid');
});

test('validTokenFor returns null when token expires within 3 hours', function () {
    ShippingProviderCredentials::create([
        'provider' => 'dpd',
        'token' => 'tok_dpd_soon',
        'expires_at' => Carbon::now('UTC')->addHours(2),
    ]);

    expect(ShippingProviderCredentials::validTokenFor('dpd'))->toBeNull();
});
