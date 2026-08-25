<?php

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;

uses(\Lunar\Tests\Admin\Feature\Filament\TestCase::class)
    ->group('resource.discount');

beforeEach(function () {
    $this->asStaff();
});

it('can render discount edit page', function () {
    get(
        \Lunar\Admin\Filament\Resources\DiscountResource::getUrl(
            'edit',
            ['record' => \Lunar\Models\Discount::factory()->create()]
        )
    )->assertSuccessful();
});

it('can edit discount', function () {
    $discount = \Lunar\Models\Discount::factory()->create();
    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'name' => 'Updated Name',
        'handle' => 'updated_name',
    ])->call('save')->assertHasNoErrors();

    assertDatabaseHas(\Lunar\Models\Discount::class, [
        'name' => 'Updated Name',
        'handle' => 'updated_name',
    ]);
});

it('can validate start and end date', function () {
    $discount = \Lunar\Models\Discount::factory()->create();
    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'starts_at' => now(),
        'ends_at' => now()->subWeek(),
    ])->call('save')->assertHasFormErrors([
        'starts_at' => 'before',
    ]);
});

it('requires a minimum cart amount for fixed value discounts', function () {
    $currency = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => 'FIXED10',
        'data.fixed_value' => true,
        'data.fixed_values.'.$currency->code => 10,
        'data.min_prices.'.$currency->code => null,
    ])->call('save')
        ->assertNotified(__('lunarpanel::discount.notifications.fixed_value_requires_minimum_cart_amount'));

    assertDatabaseMissing(\Lunar\Models\Discount::class, [
        'id' => $discount->id,
        'coupon' => 'FIXED10',
    ]);
});

it('requires the minimum cart amount to be at least the fixed value', function () {
    $currency = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => 'FIXED10',
        'data.fixed_value' => true,
        'data.fixed_values.'.$currency->code => 10,
        'data.min_prices.'.$currency->code => 5,
    ])->call('save')
        ->assertNotified(__('lunarpanel::discount.notifications.minimum_cart_amount_below_fixed_value'));

    assertDatabaseMissing(\Lunar\Models\Discount::class, [
        'id' => $discount->id,
        'coupon' => 'FIXED10',
    ]);
});

it('can save a fixed value discount when the minimum cart amount covers the fixed value', function () {
    $currency = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => 'FIXED10',
        'data.fixed_value' => true,
        'data.fixed_values.'.$currency->code => 10,
        'data.min_prices.'.$currency->code => 10,
    ])->call('save')->assertHasNoErrors();

    assertDatabaseHas(\Lunar\Models\Discount::class, [
        'id' => $discount->id,
        'coupon' => 'FIXED10',
    ]);
});

it('can save a fixed value discount without a coupon code', function () {
    $currency = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => null,
        'data.fixed_value' => true,
        'data.fixed_values.'.$currency->code => 10,
        'data.min_prices.'.$currency->code => 10,
    ])->call('save')->assertHasNoErrors();

    assertDatabaseHas(\Lunar\Models\Discount::class, [
        'id' => $discount->id,
        'coupon' => null,
    ]);
});

it('does not require a minimum cart amount for fixed value discounts without a coupon code', function () {
    $currency = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => null,
        'data.fixed_value' => true,
        'data.fixed_values.'.$currency->code => 10,
        'data.min_prices.'.$currency->code => null,
    ])->call('save')->assertHasNoErrors();

    assertDatabaseHas(\Lunar\Models\Discount::class, [
        'id' => $discount->id,
        'coupon' => null,
    ]);
});

it('does not require the minimum cart amount to cover the fixed value when there is no coupon code', function () {
    $currency = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => null,
        'data.fixed_value' => true,
        'data.fixed_values.'.$currency->code => 10,
        'data.min_prices.'.$currency->code => 5,
    ])->call('save')->assertHasNoErrors()
        ->assertNotNotified(__('lunarpanel::discount.notifications.minimum_cart_amount_below_fixed_value'));

    assertDatabaseHas(\Lunar\Models\Discount::class, [
        'id' => $discount->id,
        'coupon' => null,
    ]);
});

it('only shows one toast per problem category across multiple currencies', function () {
    $usd = \Lunar\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
        'enabled' => true,
    ]);

    $gbp = \Lunar\Models\Currency::factory()->create([
        'code' => 'GBP',
        'enabled' => true,
    ]);

    $discount = \Lunar\Models\Discount::factory()->create();

    \Livewire\Livewire::test(\Lunar\Admin\Filament\Resources\DiscountResource\Pages\EditDiscount::class,
        ['record' => $discount->getKey()]
    )->fillForm([
        'coupon' => 'FIXED10',
        'data.fixed_value' => true,
        'data.fixed_values.'.$usd->code => 10,
        'data.fixed_values.'.$gbp->code => 10,
        'data.min_prices.'.$usd->code => null,
        'data.min_prices.'.$gbp->code => null,
    ])->call('save');

    $notificationsComponent = new \Filament\Notifications\Livewire\Notifications;
    $notificationsComponent->mount();

    expect($notificationsComponent->notifications)->toHaveCount(1);
    expect($notificationsComponent->notifications->first()->getTitle())
        ->toBe(__('lunarpanel::discount.notifications.fixed_value_requires_minimum_cart_amount'));
});
