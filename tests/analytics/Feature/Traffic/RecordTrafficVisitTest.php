<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Lunar\Analytics\Traffic\Http\Middleware\RecordTrafficVisit;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Models\Cart;
use Lunar\Tests\Analytics\TestCase;
use Lunar\Tests\Core\Stubs\User;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    config(['lunar.analytics.traffic.enabled' => true]);

    Route::middleware(['web', RecordTrafficVisit::class])->group(function () {
        Route::get('/storefront', fn () => response('<html><body>Shop</body></html>'));
        Route::get('/storefront/products/{slug}', fn () => response('<html><body>Product</body></html>'));
        Route::post('/storefront', fn () => response('<html><body>Posted</body></html>'));
        Route::get('/storefront/missing', fn () => response('<html><body>Not found</body></html>', 404));
        Route::get('/storefront/redirect', fn () => redirect('/storefront'));
        Route::get('/storefront/image', fn () => response('binary', 200, ['Content-Type' => 'image/jpeg']));
    });

    $this->browserUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    $this->withHeaders(['User-Agent' => $this->browserUserAgent]);
});

test('an html page view from google records an organic google visit', function () {
    $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();

    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->medium, $visit->landing_path])->toBe(['google', 'organic', '/storefront']);
});

test('bots and empty user agents record nothing', function (string $userAgent) {
    $this->get('/storefront', ['Referer' => 'https://www.google.com/', 'User-Agent' => $userAgent])->assertOk();

    expect(TrafficVisit::count())->toBe(0);
})->with([
    'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
    'slackbot' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'],
    'telegram' => ['TelegramBot (like TwitterBot)'],
    'empty' => [''],
]);

test('a cubot phone in an in-app browser is recorded', function () {
    $userAgent = 'Mozilla/5.0 (Linux; Android 11; CUBOT X30 Build/RP1A.200720.011; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/130.0.0.0 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/480.0.0.0;]';

    $this->get('/storefront', ['Referer' => 'https://www.google.com/', 'User-Agent' => $userAgent])->assertOk();

    expect(TrafficVisit::count())->toBe(1);
});

test('a shop can extend the bot pattern', function () {
    config(['lunar.analytics.traffic.bot_user_agent_pattern' => '/bot|acmewatch/i']);

    $this->get('/storefront', ['Referer' => 'https://www.google.com/', 'User-Agent' => 'AcmeWatch/2.0'])->assertOk();

    expect(TrafficVisit::count())->toBe(0);
});

test('responses other than a 200 html page record nothing', function (string $path, int $status) {
    $this->get($path, ['Referer' => 'https://www.google.com/'])->assertStatus($status);

    expect(TrafficVisit::count())->toBe(0);
})->with([
    'not found' => ['/storefront/missing', 404],
    'redirect' => ['/storefront/redirect', 302],
    'image' => ['/storefront/image', 200],
]);

test('a prefetched page from google starts its visit at the next same-host request', function (array $prefetchHeaders) {
    $this->get('/storefront/products/phone', ['Referer' => 'https://www.google.com/', ...$prefetchHeaders])->assertOk();

    expect(TrafficVisit::count())->toBe(0);

    $this->get('/storefront', ['Referer' => 'http://localhost/storefront/products/phone'])->assertOk();

    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->medium, $visit->landing_path, $visit->referrer_host])
        ->toBe(['google', 'organic', '/storefront/products/phone', 'google.com']);
})->with([
    'sec-purpose prefetch' => [['Sec-Purpose' => 'prefetch']],
    'purpose prefetch' => [['Purpose' => 'prefetch']],
    'sec-purpose prerender' => [['Sec-Purpose' => 'prefetch;prerender']],
]);

test('a prefetch with no later request records nothing', function () {
    $this->get('/storefront/products/phone', ['Referer' => 'https://www.google.com/', 'Sec-Purpose' => 'prefetch'])
        ->assertOk()
        ->assertSessionHas(TrafficVisitRecorder::PENDING_SESSION_KEY);

    expect(TrafficVisit::count())->toBe(0);
});

test('an arrival from facebook after a prefetch records only facebook', function () {
    $this->get('/storefront/products/phone', ['Referer' => 'https://www.google.com/', 'Sec-Purpose' => 'prefetch;prerender'])->assertOk();
    $this->get('/storefront', ['Referer' => 'https://l.facebook.com/'])->assertOk();
    $this->get('/storefront/products/phone', ['Referer' => 'http://localhost/storefront'])->assertOk();

    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->medium, $visit->landing_path])->toBe(['facebook', 'social', '/storefront']);
});

test('a prefetch with a bot user agent stores no pending visit', function () {
    $this->get('/storefront/products/phone', [
        'Referer' => 'https://www.google.com/',
        'Sec-Purpose' => 'prefetch',
        'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    ])->assertOk()->assertSessionMissing(TrafficVisitRecorder::PENDING_SESSION_KEY);

    $this->get('/storefront', ['Referer' => 'http://localhost/storefront/products/phone'])->assertOk();

    expect(TrafficVisit::count())->toBe(0);
});

test('head requests record nothing', function () {
    $this->call('HEAD', '/storefront', server: [
        'HTTP_REFERER' => 'https://www.google.com/',
        'HTTP_USER_AGENT' => $this->browserUserAgent,
    ])->assertOk();

    expect(TrafficVisit::count())->toBe(0);
});

test('post requests record nothing', function () {
    $this->post('/storefront', [], ['Referer' => 'https://www.google.com/'])->assertOk();

    expect(TrafficVisit::count())->toBe(0);
});

test('nothing is recorded when recording is disabled', function () {
    config(['lunar.analytics.traffic.enabled' => false]);

    $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();

    expect(TrafficVisit::count())->toBe(0);
});

test('a visitor who denied analytics cookies is still recorded and gets no new cookie', function () {
    $response = $this->withUnencryptedCookie('cf_consent', json_encode(['analytics' => false, 'marketing' => false]))
        ->get('/storefront', ['Referer' => 'https://www.google.com/'])
        ->assertOk();

    $cookieNames = collect($response->headers->getCookies())->map->getName()->sort()->values()->all();

    expect(TrafficVisit::query()->where('source', 'google')->where('medium', 'organic')->count())->toBe(1)
        ->and($cookieNames)->toBe(collect([config('session.cookie'), 'XSRF-TOKEN'])->sort()->values()->all());
});

test('a logged-in staff member records nothing', function () {
    $this->actingAs($this->makeStaff(), 'staff')
        ->get('/storefront', ['Referer' => 'https://www.google.com/'])
        ->assertOk();

    expect(TrafficVisit::count())->toBe(0);
});

test('a logged-in customer whose active cart has a traffic source records nothing', function () {
    $user = User::factory()->create();
    $visit = TrafficVisit::factory()->create(['source' => 'facebook', 'medium' => 'social']);
    Cart::factory()->create([
        'user_id' => $user->id,
        'meta' => ['traffic_source' => ['visit_id' => $visit->id, 'source' => 'facebook', 'medium' => 'social', 'campaign' => null]],
    ]);

    $this->actingAs($user)
        ->get('/storefront', ['Referer' => 'https://www.google.com/'])
        ->assertOk();

    expect(TrafficVisit::count())->toBe(1);
});
