<?php

use Carbon\CarbonImmutable;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Lunar\Analytics\Traffic\Http\Controllers\ConfirmTrafficVisitController;
use Lunar\Analytics\Traffic\Http\Middleware\RecordTrafficVisit;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Reports\TrafficReportQuery;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    config(['lunar.analytics.traffic.enabled' => true]);

    Route::middleware(['web', RecordTrafficVisit::class])->group(function () {
        Route::get('/storefront', fn () => response('<html><body>Shop</body></html>'));
        Route::get('/storefront/products/{slug}', fn () => response('<html><body>Product</body></html>'));
        Route::get('/storefront/with-script', fn () => response(
            '<html><body>'.view('lunar.analytics::traffic.presence-script', ['nonce' => 'n0nce'])->render().'</body></html>'
        ));
    });

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36']);
});

/**
 * Forget the session in memory, so the next request only finds what was saved under its cookie.
 */
function forgetSessionInMemory(): string
{
    $sessionId = session()->getId();

    app('session')->driver()->flush();

    return $sessionId;
}

test('the first page view of a session stores its visit unconfirmed, and the report does not count it', function () {
    $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();

    $visit = TrafficVisit::sole();
    $report = new TrafficReportQuery(CarbonImmutable::today(), CarbonImmutable::today());

    expect([$visit->source, $visit->medium, $visit->confirmed_at])->toBe(['google', 'organic', null])
        ->and($report->totals()['visits'])->toBe(0);
});

test('the session\'s next page view confirms its visit without starting another', function () {
    $this->get('/storefront/products/phone', ['Referer' => 'https://www.google.com/'])->assertOk();
    $this->get('/storefront', ['Referer' => 'http://localhost/storefront/products/phone'])->assertOk();

    $visit = TrafficVisit::sole();
    $report = new TrafficReportQuery(CarbonImmutable::today(), CarbonImmutable::today());

    expect($visit->confirmed_at)->not->toBeNull()
        ->and($visit->landing_path)->toBe('/storefront/products/phone')
        ->and(session(TrafficVisitRecorder::CONFIRMED_SESSION_KEY))->toBe($visit->id)
        ->and($report->totals()['visits'])->toBe(1);
});

test('the presence route confirms the visit without saving the session or setting a cookie', function () {
    $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();
    $sessionId = forgetSessionInMemory();

    $response = $this->withCookie(config('session.cookie'), $sessionId)
        ->post('/lunar/presence')
        ->assertNoContent();

    expect(TrafficVisit::sole()->confirmed_at)->not->toBeNull()
        ->and($response->headers->getCookies())->toBe([])
        ->and(app('session')->driver()->getHandler()->read($sessionId))->not->toContain(TrafficVisitRecorder::CONFIRMED_SESSION_KEY);
});

test('a session whose visit the presence route confirmed starts no other visit on its next page view', function () {
    $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();
    $sessionId = forgetSessionInMemory();
    $this->withCookie(config('session.cookie'), $sessionId)->post('/lunar/presence')->assertNoContent();

    $this->withCookie(config('session.cookie'), $sessionId)
        ->get('/storefront/products/phone', ['Referer' => 'http://localhost/storefront'])
        ->assertOk();

    expect(TrafficVisit::sole()->confirmed_at)->not->toBeNull();
});

test('the presence route confirms nothing for a session without a visit, a prefetch or a staff member', function (Closure $setUp) {
    $setUp->call($this);

    expect(TrafficVisit::whereNotNull('confirmed_at')->count())->toBe(0);
})->with([
    'no visit' => [function () {
        $this->post('/lunar/presence')->assertNoContent();
    }],
    'unknown session cookie' => [function () {
        $this->withCookie(config('session.cookie'), str_repeat('a', 40))->post('/lunar/presence')->assertNoContent();
    }],
    'prefetch' => [function () {
        $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();
        $this->post('/lunar/presence', [], ['Sec-Purpose' => 'prefetch'])->assertNoContent();
    }],
    'staff member' => [function () {
        $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();
        $this->actingAs($this->makeStaff(), 'staff')->post('/lunar/presence')->assertNoContent();
    }],
    'recording disabled' => [function () {
        $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();
        config(['lunar.analytics.traffic.enabled' => false]);
        $this->post('/lunar/presence')->assertNoContent();
    }],
]);

test('a failure in the presence route is reported and still answers no content', function () {
    Exceptions::fake();

    $exception = new RuntimeException('Confirmation failed.');
    $this->partialMock(TrafficVisitRecorder::class)->shouldReceive('confirm')->once()->andThrow($exception);

    $this->post('/lunar/presence')->assertNoContent();

    expect(Exceptions::reported())->toBe([$exception]);
});

test('a prefetched visit is left to the session\'s next page view, which records it confirmed', function () {
    $this->get('/storefront/products/phone', ['Referer' => 'https://www.google.com/', 'Sec-Purpose' => 'prefetch'])->assertOk();
    $this->post('/lunar/presence')->assertNoContent();

    expect(TrafficVisit::count())->toBe(0);

    $this->get('/storefront', ['Referer' => 'http://localhost/storefront/products/phone'])->assertOk();

    $visit = TrafficVisit::sole();

    expect([$visit->source, $visit->landing_path])->toBe(['google', '/storefront/products/phone'])
        ->and($visit->confirmed_at)->not->toBeNull();
});

test('a campaign visit started by a session that came back is stored confirmed', function () {
    $this->get('/storefront', ['Referer' => 'https://www.google.com/'])->assertOk();
    $this->get('/storefront?utm_source=newsletter&utm_medium=email')->assertOk();

    expect(TrafficVisit::query()->orderBy('id')->get()->map(fn (TrafficVisit $visit): array => [$visit->source, $visit->confirmed_at !== null])->all())
        ->toBe([['google', true], ['newsletter', true]]);
});

test('crawlers that never come back leave only unconfirmed visits', function () {
    foreach (['/storefront/products/a', '/storefront/products/b', '/storefront/products/c'] as $path) {
        $this->get($path)->assertOk();
        forgetSessionInMemory();
    }

    $report = new TrafficReportQuery(CarbonImmutable::today(), CarbonImmutable::today());

    expect(TrafficVisit::count())->toBe(3)
        ->and(TrafficVisit::whereNull('confirmed_at')->count())->toBe(3)
        ->and($report->totals()['visits'])->toBe(0);
});

test('the presence script is on the first page of a session only', function () {
    $this->get('/storefront/with-script', ['Referer' => 'https://www.google.com/'])
        ->assertOk()
        ->assertSee('nonce="n0nce"', false)
        ->assertSee('navigator.sendBeacon', false)
        ->assertSee('\/lunar\/presence', false)
        ->assertSee('prerenderingchange', false);

    $this->get('/storefront/with-script', ['Referer' => 'http://localhost/storefront'])
        ->assertOk()
        ->assertDontSee('sendBeacon', false);
});

test('the presence script is left out without recording or for a staff member', function (Closure $setUp) {
    $setUp->call($this);

    $this->get('/storefront/with-script')->assertOk()->assertDontSee('sendBeacon', false);
})->with([
    'recording disabled' => [function () {
        config(['lunar.analytics.traffic.enabled' => false]);
    }],
    'staff member' => [function () {
        $this->actingAs($this->makeStaff(), 'staff');
    }],
]);

test('the package registers the presence route outside the web group, with cookie decryption only', function () {
    $route = Route::getRoutes()->getByName('lunar.analytics.traffic.presence');

    expect($route->uri())->toBe('lunar/presence')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->getActionName())->toBe(ConfirmTrafficVisitController::class)
        ->and($route->gatherMiddleware())->toBe([EncryptCookies::class]);
});
