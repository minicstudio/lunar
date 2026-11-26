<?php

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Lunar\Actions\Carts\MergeCart;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Analytics\Conversions\Services\ConversionEventRecorder;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    config(['lunar.analytics.conversions.enabled' => true]);
});

function conversionRecorder(): ConversionEventRecorder
{
    return app(ConversionEventRecorder::class);
}

/**
 * The cart's recorded event types, oldest first.
 *
 * @return list<ConversionEventType>
 */
function conversionTypesOf(Cart $cart): array
{
    return ConversionEvent::query()
        ->where('cart_id', $cart->id)
        ->orderBy('id')
        ->get()
        ->map(fn (ConversionEvent $event): ConversionEventType => $event->type)
        ->all();
}

/**
 * A product variant in stock with a price in the default currency.
 */
function conversionPurchasable(): ProductVariant
{
    $purchasable = ProductVariant::factory()->create(['stock' => 10]);

    Price::factory()->create([
        'price' => 100,
        'min_quantity' => 1,
        'currency_id' => Currency::getDefault()->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    return $purchasable;
}

test('the first add to a cart records its start and the add, at the same time', function () {
    $cart = Cart::factory()->create();

    conversionRecorder()->addToCart($cart);

    $events = ConversionEvent::query()->orderBy('id')->get();

    expect(conversionTypesOf($cart))->toBe([ConversionEventType::CartStarted, ConversionEventType::AddToCart])
        ->and($events[0]->created_at->equalTo($events[1]->created_at))->toBeTrue();
});

test('later adds to the same cart record only adds', function () {
    $cart = Cart::factory()->create();

    conversionRecorder()->addToCart($cart);
    conversionRecorder()->addToCart($cart);
    conversionRecorder()->addToCart($cart);

    expect(conversionTypesOf($cart))->toBe([
        ConversionEventType::CartStarted,
        ConversionEventType::AddToCart,
        ConversionEventType::AddToCart,
        ConversionEventType::AddToCart,
    ]);
});

test('a cart without a reported add has no start', function () {
    Cart::factory()->create();

    expect(ConversionEvent::count())->toBe(0);
});

test('a checkout start is recorded once per cart, and again for a new cart', function () {
    $cart = Cart::factory()->create();
    conversionRecorder()->addToCart($cart);

    conversionRecorder()->beginCheckout($cart);
    conversionRecorder()->beginCheckout($cart);
    conversionRecorder()->beginCheckout($cart);

    $newCart = Cart::factory()->create();
    conversionRecorder()->addToCart($newCart);
    conversionRecorder()->beginCheckout($newCart);

    expect(ConversionEvent::query()->where('type', ConversionEventType::BeginCheckout)->where('cart_id', $cart->id)->count())->toBe(1)
        ->and(ConversionEvent::query()->where('type', ConversionEventType::BeginCheckout)->where('cart_id', $newCart->id)->count())->toBe(1);
});

test('nothing is recorded while recording is off', function () {
    config(['lunar.analytics.conversions.enabled' => false]);
    $cart = Cart::factory()->create();

    conversionRecorder()->addToCart($cart);
    conversionRecorder()->beginCheckout($cart);

    expect(conversionRecorder()->enabled())->toBeFalse()
        ->and(ConversionEvent::count())->toBe(0);
});

test('events are recorded with traffic recording off, and no visit is recorded', function () {
    config(['lunar.analytics.traffic.enabled' => false]);
    $cart = Cart::factory()->create();

    conversionRecorder()->addToCart($cart);

    expect(conversionTypesOf($cart))->toBe([ConversionEventType::CartStarted, ConversionEventType::AddToCart])
        ->and(TrafficVisit::count())->toBe(0);
});

test('nothing is recorded while the admin panel serves the request or a staff member is logged in', function (Closure $setUp) {
    $setUp->call($this);
    $cart = Cart::factory()->create();

    conversionRecorder()->addToCart($cart);
    conversionRecorder()->beginCheckout($cart);

    expect(ConversionEvent::count())->toBe(0);
})->with([
    'admin panel serving' => [function () {
        Filament::setServingStatus(true);
    }],
    'staff logged in' => [function () {
        $this->actingAs($this->makeStaff(), 'staff');
    }],
]);

test('nothing is recorded for an unsaved cart', function () {
    $cart = Cart::factory()->make();

    conversionRecorder()->addToCart($cart);
    conversionRecorder()->beginCheckout($cart);

    expect(ConversionEvent::count())->toBe(0);
});

test('cart line changes the storefront does not report record nothing', function () {
    $purchasable = conversionPurchasable();
    $customerCart = Cart::factory()->create(['currency_id' => Currency::getDefault()->id]);
    $guestCart = Cart::factory()->create(['currency_id' => Currency::getDefault()->id]);

    $customerCart->add($purchasable, 1);
    $guestCart->add($purchasable, 2);
    $customerCart->updateLine($customerCart->refresh()->lines->first()->id, 3);
    app(MergeCart::class)->execute($customerCart, $guestCart);

    expect($customerCart->refresh()->lines->first()->quantity)->toBe(5)
        ->and(ConversionEvent::count())->toBe(0);
});

test('a failed insert is reported and does not interrupt the shopper', function () {
    Exceptions::fake();
    $cart = Cart::factory()->create();
    Schema::drop('lunar_conversion_events');

    conversionRecorder()->addToCart($cart);
    conversionRecorder()->beginCheckout($cart);

    Exceptions::assertReported(QueryException::class);
    Exceptions::assertReportedCount(2);
});

test('recording ignores a consent cookie that denies analytics and queues no cookie', function () {
    $request = Request::create('https://shop.test/products/phone', 'GET', cookies: [
        'cf_consent' => json_encode(['analytics' => false, 'marketing' => false]),
    ]);
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);

    $cart = Cart::factory()->create();

    conversionRecorder()->addToCart($cart);
    conversionRecorder()->beginCheckout($cart);

    expect(conversionTypesOf($cart))->toBe([
        ConversionEventType::CartStarted,
        ConversionEventType::AddToCart,
        ConversionEventType::BeginCheckout,
    ])->and(Cookie::getQueuedCookies())->toBe([]);
});
