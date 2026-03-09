<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Contracts\ErpProviderInterface;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Exceptions\ErpInitializationException;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpProvider;
use Lunar\ERP\Services\ErpManager;

beforeEach(function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister']);
    Config::set('lunar.erp.magister', [
        'enabled' => true,
        'provider_class' => MagisterErpProvider::class,
        'client_class' => MagisterApiClient::class,
    ]);
});

it('returns provider instance when enabled', function () {
    $mockClient = \Mockery::mock(ErpApiClientInterface::class);
    app()->singleton(MagisterErpProvider::class, fn () => new MagisterErpProvider($mockClient));

    $manager = new ErpManager(['magister']);
    $provider = $manager->getProvider(ErpProviderEnum::magister);

    expect($provider)->toBeInstanceOf(ErpProviderInterface::class)
        ->and($provider)->toBeInstanceOf(MagisterErpProvider::class);
});

it('throws when global erp disabled', function () {
    Config::set('lunar.erp.enabled', false);
    $manager = new ErpManager(['magister']);

    $this->expectException(ErpInitializationException::class);
    $manager->getProvider(ErpProviderEnum::magister);
});

it('throws when provider disabled', function () {
    Config::set('lunar.erp.magister.enabled', false);
    $manager = new ErpManager(['magister']);

    $this->expectException(ErpInitializationException::class);
    $manager->getProvider(ErpProviderEnum::magister);
});
