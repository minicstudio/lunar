<?php

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Lunar\Actions\Carts\MergeCart;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
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

/**
 * Add a product line to the cart.
 */
function addCartLine(Cart $cart): CartLine
{
    return CartLine::factory()->create(['cart_id' => $cart->id]);
}

/**
 * The cart's stored traffic source.
 *
 * @return array{visit_id: int, source: string, medium: ?string, campaign: ?string}|null
 */
function trafficSourceOf(Cart $cart): ?array
{
    return $cart->fresh()->meta['traffic_source'] ?? null;
}

test('an empty cart gets no traffic source', function () {
    putCurrentVisit('facebook', 'social');

    $cart = Cart::factory()->create();

    expect(trafficSourceOf($cart))->toBeNull();
});

test('the first product added during a visit stores the visit source without landing path or referrer host', function () {
    $visit = putCurrentVisit('facebook', 'social', 'autumn');
    $cart = Cart::factory()->create();

    addCartLine($cart);

    expect(trafficSourceOf($cart))->toBe($visit)
        ->and(array_keys(trafficSourceOf($cart)))->toBe(['visit_id', 'source', 'medium', 'campaign']);
});

test('the cart a product is added to carries the source at once', function () {
    $visit = putCurrentVisit('facebook', 'social');
    $purchasable = ProductVariant::factory()->create(['stock' => 10]);
    Price::factory()->create([
        'price' => 100,
        'min_quantity' => 1,
        'currency_id' => Currency::getDefault()->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);
    $cart = Cart::factory()->create(['currency_id' => Currency::getDefault()->id]);

    $cart->add($purchasable);

    expect($cart->meta['traffic_source'])->toBe($visit);
});

test('the cart meta is kept', function () {
    $visit = putCurrentVisit('facebook', 'social');
    $cart = Cart::factory()->create(['meta' => ['shippingType' => 'courier', 'is_guest' => true]]);

    addCartLine($cart);

    expect($cart->fresh()->meta->getArrayCopy())->toBe([
        'shippingType' => 'courier',
        'is_guest' => true,
        'traffic_source' => $visit,
    ]);
});

test('a later visit does not change the cart source', function () {
    $facebook = putCurrentVisit('facebook', 'social');
    $cart = Cart::factory()->create();
    addCartLine($cart);

    putCurrentVisit('google', 'organic');
    addCartLine($cart);
    $cart->fresh()->update(['coupon_code' => 'AUTUMN']);

    expect(trafficSourceOf($cart))->toBe($facebook);
});

test('an empty cart gets the source of the visit in which its first product is added', function () {
    putCurrentVisit('facebook', 'social');
    $cart = Cart::factory()->create();

    $google = putCurrentVisit('google', 'organic');
    addCartLine($cart);

    expect(trafficSourceOf($cart))->toBe($google);
});

test('lines merged into an empty cart give it the current visit', function () {
    $visit = putCurrentVisit('facebook', 'social');
    $guestCart = Cart::factory()->create();
    addCartLine($guestCart);
    $customerCart = Cart::factory()->create();

    app(MergeCart::class)->execute($customerCart, $guestCart->fresh());

    expect(trafficSourceOf($customerCart))->toBe($visit);
});

test('the first product on a prefetched landing page records the pending visit and carries it', function () {
    session()->put(TrafficVisitRecorder::PENDING_SESSION_KEY, [
        'source' => 'google',
        'medium' => 'organic',
        'campaign' => null,
        'referrer_host' => 'google.com',
        'landing_path' => '/products/phone',
    ]);

    $cart = Cart::factory()->create();

    expect(TrafficVisit::count())->toBe(0);

    addCartLine($cart);
    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->medium, $visit->referrer_host, $visit->landing_path])
        ->toBe(['google', 'organic', 'google.com', '/products/phone'])
        ->and($visit->confirmed_at)->not->toBeNull()
        ->and(trafficSourceOf($cart))->toBe([
            'visit_id' => $visit->id,
            'source' => 'google',
            'medium' => 'organic',
            'campaign' => null,
        ]);
});

test('adding a product confirms the visit the cart gets', function () {
    $payload = putCurrentVisit('facebook', 'social');
    TrafficVisit::query()->whereKey($payload['visit_id'])->update(['confirmed_at' => null]);

    $cart = Cart::factory()->create();
    addCartLine($cart);

    expect(TrafficVisit::query()->findOrFail($payload['visit_id'])->confirmed_at)->not->toBeNull()
        ->and(trafficSourceOf($cart))->toBe($payload);
});

test('a failed visit confirmation is reported and the cart still gets the visit', function () {
    Exceptions::fake();
    $payload = putCurrentVisit('facebook', 'social');
    Schema::drop('lunar_traffic_visits');

    $cart = Cart::factory()->create();
    addCartLine($cart);

    Exceptions::assertReported(QueryException::class);
    expect(trafficSourceOf($cart))->toBe($payload);
});

test('a cart gets no traffic source without a trackable visit', function (Closure $setUp) {
    putCurrentVisit('facebook', 'social');
    $setUp->call($this);
    $cart = Cart::factory()->create();

    addCartLine($cart);

    expect(trafficSourceOf($cart))->toBeNull();
})->with([
    'no visit' => [fn () => session()->forget(TrafficVisitRecorder::SESSION_KEY)],
    'recording disabled' => [fn () => config(['lunar.analytics.traffic.enabled' => false])],
    'admin panel serving' => [fn () => Filament::setServingStatus(true)],
    'staff logged in' => [function () {
        $this->actingAs($this->makeStaff(), 'staff');
    }],
]);
