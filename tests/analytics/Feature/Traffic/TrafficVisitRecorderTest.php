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
 *
 * @param  bool  $prefetch  Send it as a browser prefetch (`Sec-Purpose: prefetch`).
 */
function recorderRequest(string $path = '/', ?string $referrer = null, bool $prefetch = false): Request
{
    $server = array_filter([
        'HTTP_REFERER' => $referrer,
        'HTTP_SEC_PURPOSE' => $prefetch ? 'prefetch' : null,
    ]);

    $request = Request::create('https://shop.test'.$path, 'GET', server: $server);
    $request->setLaravelSession(app('session.store'));

    app()->instance('request', $request);

    return $request;
}

function recordPageView(string $path = '/', ?string $referrer = null, bool $prefetch = false): void
{
    app(TrafficVisitRecorder::class)->record(recorderRequest($path, $referrer, $prefetch));
}

/**
 * Prefetch a product page from Google search, leaving a pending visit in the session.
 */
function prefetchFromGoogle(): void
{
    recordPageView('/products/phone', 'https://www.google.com/', prefetch: true);
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

test('a prefetch stores a pending visit and records nothing', function () {
    prefetchFromGoogle();

    expect(TrafficVisit::count())->toBe(0)
        ->and(session(TrafficVisitRecorder::SESSION_KEY))->toBeNull()
        ->and(session(TrafficVisitRecorder::PENDING_SESSION_KEY))->toBe([
            'source' => 'google',
            'medium' => 'organic',
            'campaign' => null,
            'referrer_host' => 'google.com',
            'landing_path' => '/products/phone',
        ]);
});

test('a pending visit yields to a facebook-sourced cart in the session', function (Closure $openPage) {
    prefetchFromGoogle();
    $trafficSource = facebookTrafficSource();
    $cart = Cart::factory()->create(['meta' => ['traffic_source' => $trafficSource]]);
    session()->put(config('lunar.cart_session.session_key'), $cart->id);

    $openPage();

    expect(TrafficVisit::count())->toBe(1)
        ->and(session(TrafficVisitRecorder::SESSION_KEY))->toBe($trafficSource)
        ->and(session()->has(TrafficVisitRecorder::PENDING_SESSION_KEY))->toBeFalse();
})->with([
    'next page view' => [fn () => recordPageView('/category', 'https://shop.test/products/phone')],
    'current visit lookup' => [function () {
        recorderRequest('/livewire/update', 'https://shop.test/products/phone');
        currentVisit();
    }],
]);

test('current records a pending visit once and returns it', function () {
    prefetchFromGoogle();
    recorderRequest('/livewire/update', 'https://shop.test/products/phone');

    $first = currentVisit();
    $visit = TrafficVisit::sole();

    expect($first)->toBe([
        'visit_id' => $visit->id,
        'source' => 'google',
        'medium' => 'organic',
        'campaign' => null,
    ])
        ->and([$visit->landing_path, $visit->referrer_host])->toBe(['/products/phone', 'google.com'])
        ->and(session()->has(TrafficVisitRecorder::PENDING_SESSION_KEY))->toBeFalse()
        ->and(currentVisit())->toBe($first)
        ->and(TrafficVisit::count())->toBe(1);
});

test('current on a prefetch request returns null and records nothing', function () {
    prefetchFromGoogle();

    expect(currentVisit())->toBeNull()
        ->and(TrafficVisit::count())->toBe(0)
        ->and(session()->has(TrafficVisitRecorder::PENDING_SESSION_KEY))->toBeTrue();
});

test('a prefetch in a session that already has a visit stores no pending visit', function () {
    recordPageView('/', 'https://www.google.com/');
    $current = currentVisit();

    recordPageView('/sale?gclid=abc', 'https://www.google.com/', prefetch: true);

    expect(TrafficVisit::count())->toBe(1)
        ->and(session()->has(TrafficVisitRecorder::PENDING_SESSION_KEY))->toBeFalse()
        ->and(currentVisit())->toBe($current);
});

test('a pending visit is not recorded while recording may not happen', function (Closure $setUp) {
    prefetchFromGoogle();
    $setUp->call($this);

    recordPageView('/category', 'https://shop.test/products/phone');

    expect(currentVisit())->toBeNull()
        ->and(TrafficVisit::count())->toBe(0);
})->with([
    'recording disabled' => [fn () => config(['lunar.analytics.traffic.enabled' => false])],
    'staff logged in' => [function () {
        $this->actingAs($this->makeStaff(), 'staff');
    }],
]);

test('current is null without a session', function () {
    app()->instance('request', Request::create('https://shop.test/'));

    expect(currentVisit())->toBeNull();
});
