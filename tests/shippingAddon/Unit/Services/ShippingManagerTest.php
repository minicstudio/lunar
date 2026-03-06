<?php

uses(\Lunar\Tests\ShippingAddon\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Lunar\Addons\Shipping\Contracts\ShippingProviderInterface;
use Lunar\Addons\Shipping\Enums\ShippingProviderEnum;
use Lunar\Addons\Shipping\Exceptions\ShippingInitializationException;
use Lunar\Addons\Shipping\Providers\Dpd\DpdApiClient;
use Lunar\Addons\Shipping\Providers\Dpd\DpdShippingProvider;
use Lunar\Addons\Shipping\Services\ShippingManager;

beforeEach(function () {
    Config::set('lunar.shipping.enabled', true);
    Config::set('lunar.shipping.providers', ['dpd']);
    Config::set('lunar.shipping.dpd', [
        'enabled' => true,
        'provider_class' => DpdShippingProvider::class,
        'client_class' => DpdApiClient::class,
    ]);
});

it('returns provider instance when enabled', function () {
    $mockClient = \Mockery::mock(ShippingApiClient::class);
    app()->singleton(DpdShippingProvider::class, fn () => new DpdShippingProvider($mockClient));

    $manager = new ShippingManager(['dpd']);
    $provider = $manager->getProvider(ShippingProviderEnum::dpd);

    expect($provider)->toBeInstanceOf(ShippingProviderInterface::class)
        ->and($provider)->toBeInstanceOf(DpdShippingProvider::class);
});

it('throws when global shipping disabled', function () {
    Config::set('lunar.shipping.enabled', false);
    $manager = new ShippingManager(['dpd']);

    $this->expectException(ShippingInitializationException::class);
    $manager->getProvider(ShippingProviderEnum::dpd);
});

it('throws when provider disabled', function () {
    Config::set('lunar.shipping.dpd.enabled', false);
    $manager = new ShippingManager(['dpd']);

    $this->expectException(ShippingInitializationException::class);
    $manager->getProvider(ShippingProviderEnum::dpd);
});
