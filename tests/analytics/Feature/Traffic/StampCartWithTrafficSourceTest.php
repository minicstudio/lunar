<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\Cart;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    config(['lunar.analytics.traffic.enabled' => true]);

    $request = Request::create('https://shop.test/');
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
});

/**
 * Store a visit and make it the session's current visit.
 *
 * @return array{visit_id: int, source: string, medium: ?string, campaign: ?string}
 */
function putCurrentVisit(string $source, ?string $medium, ?string $campaign = null): array
{
    $visit = TrafficVisit::factory()->create([
        'source' => $source,
        'medium' => $medium,
        'campaign' => $campaign,
        'referrer_host' => 'l.facebook.com',
        'landing_path' => '/sale',
    ]);

    $payload = [
        'visit_id' => $visit->id,
        'source' => $source,
        'medium' => $medium,
        'campaign' => $campaign,
    ];

    session()->put(TrafficVisitRecorder::SESSION_KEY, $payload);

    return $payload;
}

test('a cart created during a visit stores the visit source without landing path or referrer host', function () {
    $visit = putCurrentVisit('facebook', 'social', 'autumn');

    $cart = Cart::factory()->create();

    expect($cart->fresh()->meta['traffic_source'])->toBe($visit)
        ->and(array_keys($cart->fresh()->meta['traffic_source']))->toBe(['visit_id', 'source', 'medium', 'campaign']);
});

test('meta passed to create is kept', function () {
    $visit = putCurrentVisit('facebook', 'social');

    $cart = Cart::factory()->create(['meta' => ['shippingType' => 'courier', 'is_guest' => true]]);

    expect($cart->fresh()->meta->getArrayCopy())->toBe([
        'shippingType' => 'courier',
        'is_guest' => true,
        'traffic_source' => $visit,
    ]);
});

test('a later visit does not change the cart source', function () {
    $facebook = putCurrentVisit('facebook', 'social');
    $cart = Cart::factory()->create();

    putCurrentVisit('google', 'organic');
    $cart->fresh()->update(['coupon_code' => 'AUTUMN']);

    expect($cart->fresh()->meta['traffic_source'])->toBe($facebook);
});

test('a cart created on a prefetched landing page records the pending visit and carries it', function () {
    session()->put(TrafficVisitRecorder::PENDING_SESSION_KEY, [
        'source' => 'google',
        'medium' => 'organic',
        'campaign' => null,
        'referrer_host' => 'google.com',
        'landing_path' => '/products/phone',
    ]);

    $cart = Cart::factory()->create();
    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->medium, $visit->referrer_host, $visit->landing_path])
        ->toBe(['google', 'organic', 'google.com', '/products/phone'])
        ->and($cart->fresh()->meta['traffic_source'])->toBe([
            'visit_id' => $visit->id,
            'source' => 'google',
            'medium' => 'organic',
            'campaign' => null,
        ]);
});

test('a cart gets no traffic source without a trackable visit', function (Closure $setUp) {
    putCurrentVisit('facebook', 'social');
    $setUp->call($this);

    $cart = Cart::factory()->create();

    expect($cart->fresh()->meta ?? [])->not->toHaveKey('traffic_source');
})->with([
    'no visit' => [fn () => session()->forget(TrafficVisitRecorder::SESSION_KEY)],
    'recording disabled' => [fn () => config(['lunar.analytics.traffic.enabled' => false])],
    'admin panel serving' => [fn () => Filament::setServingStatus(true)],
    'staff logged in' => [function () {
        $this->actingAs($this->makeStaff(), 'staff');
    }],
]);
