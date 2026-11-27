<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Models\Cart;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
});

test('the table is lunar_conversion_events with exactly four columns and no personal data', function () {
    expect((new ConversionEvent)->getTable())->toBe('lunar_conversion_events')
        ->and(Schema::getColumnListing('lunar_conversion_events'))->toEqualCanonicalizing([
            'id',
            'type',
            'cart_id',
            'created_at',
        ]);
});

test('an event stores its type, cart and time, and has no updated_at', function () {
    $cart = Cart::factory()->create();

    $event = ConversionEvent::create(['type' => ConversionEventType::CartStarted, 'cart_id' => $cart->id]);
    $stored = ConversionEvent::query()->findOrFail($event->id);

    expect($stored->type)->toBe(ConversionEventType::CartStarted)
        ->and($stored->cart_id)->toBe($cart->id)
        ->and($stored->created_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($stored->getUpdatedAtColumn())->toBeNull();
});

test('the factory gives an add to cart by default and a state per other type', function () {
    expect(ConversionEvent::factory()->create()->type)->toBe(ConversionEventType::AddToCart)
        ->and(ConversionEvent::factory()->cartStarted()->create()->type)->toBe(ConversionEventType::CartStarted)
        ->and(ConversionEvent::factory()->beginCheckout()->create()->type)->toBe(ConversionEventType::BeginCheckout);
});
