<?php

use Lunar\Addons\Shipping\Providers\Dpd\DpdApiClient;
use Lunar\Addons\Shipping\Providers\Dpd\DpdShippingProvider;
use Lunar\Addons\Shipping\Providers\Pickup\PickupApiClient;
use Lunar\Addons\Shipping\Providers\Pickup\PickupShippingProvider;

function shippingProviderConfigPath(string $provider): string
{
    return __DIR__."/../../../../packages/shipping/src/Providers/{$provider}/config.php";
}

test('pickup config enabled defaults to false from env', function () {
    $config = require shippingProviderConfigPath('Pickup');

    expect($config['enabled'])->toBeBool();
});

test('pickup config has provider_class key', function () {
    $config = require shippingProviderConfigPath('Pickup');

    expect($config)->toHaveKey('provider_class');
    expect($config['provider_class'])->toBe(PickupShippingProvider::class);
});

test('pickup config has client_class key', function () {
    $config = require shippingProviderConfigPath('Pickup');

    expect($config)->toHaveKey('client_class');
    expect($config['client_class'])->toBe(PickupApiClient::class);
});

test('dpd config enabled defaults to false from env', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config['enabled'])->toBeBool();
});

test('dpd config has provider_class key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('provider_class');
    expect($config['provider_class'])->toBe(DpdShippingProvider::class);
});

test('dpd config has client_class key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('client_class');
    expect($config['client_class'])->toBe(DpdApiClient::class);
});

test('dpd config has base_url key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('base_url');
});

test('dpd config has username key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('username');
});

test('dpd config has password key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('password');
});

test('dpd config has service_id key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('service_id');
});

test('dpd config has contents key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('contents');
});

test('dpd config has package key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('package');
});

test('dpd config has paper_size key', function () {
    $config = require shippingProviderConfigPath('Dpd');

    expect($config)->toHaveKey('paper_size');
});
