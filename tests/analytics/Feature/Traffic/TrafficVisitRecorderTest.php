<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\Cart;
use Lunar\Models\Order;
use Lunar\Tests\Analytics\TestCase;
use Lunar\Tests\Core\Stubs\User;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    config(['lunar.analytics.traffic.enabled' => true]);
});

/**
 * Build a storefront GET request on shop.test that carries the test session.
 */
function recorderRequest(string $path = '/', ?string $referrer = null): Request
{
    $request = Request::create('https://shop.test'.$path, 'GET', server: $referrer === null ? [] : ['HTTP_REFERER' => $referrer]);
    $request->setLaravelSession(app('session.store'));

    app()->instance('request', $request);

    return $request;
}

function recordPageView(string $path = '/', ?string $referrer = null): void
{
    app(TrafficVisitRecorder::class)->record(recorderRequest($path, $referrer));
}

function currentVisit(): ?array
{
    return app(TrafficVisitRecorder::class)->current();
}

function startNewSession(): void
{
    app('session.store')->flush();
}

/**
 * A stored Facebook visit and the traffic source a cart created in it carries.
 *
 * @return array{visit_id: int, source: string, medium: string, campaign: null}
 */
function facebookTrafficSource(): array
{
    $visit = TrafficVisit::factory()->create([
        'source' => 'facebook',
        'medium' => 'social',
        'referrer_host' => 'l.facebook.com',
    ]);

    return [
        'visit_id' => $visit->id,
        'source' => 'facebook',
        'medium' => 'social',
        'campaign' => null,
    ];
}

test('the first page view of a session records one visit', function () {
    recordPageView('/products/phone', 'https://www.google.com/');

    $visit = TrafficVisit::sole();

    expect($visit->source)->toBe('google')
        ->and($visit->medium)->toBe('organic')
        ->and($visit->landing_path)->toBe('/products/phone')
        ->and(currentVisit())->toBe([
            'visit_id' => $visit->id,
            'source' => 'google',
            'medium' => 'organic',
            'campaign' => null,
        ]);
});

test('repeat page views in the same session record no visit', function () {
    recordPageView('/', 'https://www.google.com/');
    recordPageView('/category', 'https://shop.test/');
    recordPageView('/products/phone', 'https://shop.test/category');
    recordPageView('/cart');

    expect(TrafficVisit::count())->toBe(1);
});

test('a click id mid-session records a new visit that becomes current', function () {
    recordPageView('/', 'https://www.google.com/');
    recordPageView('/sale?gclid=abc', 'https://www.google.com/');

    $latest = TrafficVisit::query()->latest('id')->first();

    expect(TrafficVisit::count())->toBe(2)
        ->and([$latest->source, $latest->medium])->toBe(['google', 'cpc'])
        ->and(currentVisit()['visit_id'])->toBe($latest->id);
});

test('reloading the same tagged URL records no new visit', function () {
    $url = '/?utm_source=newsletter&utm_medium=email&utm_campaign=nov';

    recordPageView($url);
    recordPageView($url);

    expect(TrafficVisit::count())->toBe(1);
});

test('an external return mid-session records no visit', function () {
    recordPageView('/', 'https://www.google.com/');
    $current = currentVisit();

    recordPageView('/checkout/confirmation', 'https://secure.bank.example/3ds');

    expect(TrafficVisit::count())->toBe(1)
        ->and(currentVisit())->toBe($current);
});

test('a same-host referrer in a new session records no visit', function () {
    recordPageView('/', 'https://www.google.com/');
    startNewSession();

    recordPageView('/account', 'https://shop.test/logout');

    expect(TrafficVisit::count())->toBe(1)
        ->and(currentVisit())->toBeNull();
});

test('a referrer with a path and query stores only its host and the landing path', function () {
    recordPageView('/sale?utm_source=blog&ref=x', 'https://blog.example.com/post?id=7');

    $visit = TrafficVisit::sole();

    expect($visit->referrer_host)->toBe('blog.example.com')
        ->and($visit->landing_path)->toBe('/sale')
        ->and($visit->source)->toBe('blog');
});

test('a returning customer whose active cart has a source continues that visit', function () {
    $user = User::factory()->create();
    $trafficSource = facebookTrafficSource();
    Cart::factory()->create(['user_id' => $user->id, 'meta' => ['traffic_source' => $trafficSource]]);
    $this->actingAs($user);

    recordPageView('/', 'https://www.google.com/');

    expect(TrafficVisit::count())->toBe(1)
        ->and(session(TrafficVisitRecorder::SESSION_KEY))->toBe($trafficSource)
        ->and(currentVisit())->toBe($trafficSource);

    recordPageView('/checkout/confirmation', 'https://secure.bank.example/');

    expect(TrafficVisit::count())->toBe(1)
        ->and(currentVisit())->toBe($trafficSource);
});

test('an ad click while holding a sourced cart records nothing until its order is placed', function () {
    $trafficSource = facebookTrafficSource();
    $cart = Cart::factory()->create(['meta' => ['traffic_source' => $trafficSource]]);
    session()->put(config('lunar.cart_session.session_key'), $cart->id);
    session()->put(TrafficVisitRecorder::SESSION_KEY, $trafficSource);

    recordPageView('/?gclid=abc');

    expect(TrafficVisit::count())->toBe(1)
        ->and(currentVisit())->toBe($trafficSource);

    Order::factory()->create(['cart_id' => $cart->id, 'placed_at' => now()]);

    recordPageView('/?gclid=abc');

    $latest = TrafficVisit::query()->latest('id')->first();

    expect(TrafficVisit::count())->toBe(2)
        ->and([$latest->source, $latest->medium])->toBe(['google', 'cpc'])
        ->and(currentVisit()['visit_id'])->toBe($latest->id);
});

test('an active cart without a traffic source does not stop a visit', function () {
    $user = User::factory()->create();
    Cart::factory()->create(['user_id' => $user->id, 'meta' => ['shippingType' => 'courier']]);
    $this->actingAs($user);

    recordPageView('/', 'https://www.google.com/');

    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->medium])->toBe(['google', 'organic']);
});

test('the cart lookup creates no cart when the session cart has a placed order', function () {
    $cart = Cart::factory()->create(['meta' => ['traffic_source' => facebookTrafficSource()]]);
    Order::factory()->create(['cart_id' => $cart->id, 'placed_at' => now()]);
    session()->put(config('lunar.cart_session.session_key'), $cart->id);
    $carts = Cart::count();

    recordPageView('/', 'https://www.google.com/');

    expect(Cart::count())->toBe($carts)
        ->and(TrafficVisit::query()->where('source', 'google')->count())->toBe(1);
});

test('nothing is recorded or current while the admin panel serves the request', function () {
    session()->put(TrafficVisitRecorder::SESSION_KEY, facebookTrafficSource());
    Filament::setServingStatus(true);

    recordPageView('/?gclid=abc', 'https://www.google.com/');

    expect(TrafficVisit::count())->toBe(1)
        ->and(currentVisit())->toBeNull();
});

test('nothing is recorded or current while a staff member is logged in', function () {
    session()->put(TrafficVisitRecorder::SESSION_KEY, facebookTrafficSource());
    $this->actingAs($this->makeStaff(), 'staff');

    recordPageView('/?gclid=abc', 'https://www.google.com/');

    expect(TrafficVisit::count())->toBe(1)
        ->and(currentVisit())->toBeNull();
});

test('current is null when recording is disabled', function () {
    recordPageView('/', 'https://www.google.com/');
    expect(currentVisit())->not->toBeNull();

    config(['lunar.analytics.traffic.enabled' => false]);

    expect(currentVisit())->toBeNull();
});

test('current is null without a session', function () {
    app()->instance('request', Request::create('https://shop.test/'));

    expect(currentVisit())->toBeNull();
});
