<?php

uses(\Lunar\Tests\ERP\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Providers\Smartbill\SmartbillErpProvider;

it('isEnabled uses smartbill flag', function () {
    Config::set('lunar.erp.smartbill.enabled', true);
    $prov = new SmartbillErpProvider(\Mockery::mock(ErpApiClientInterface::class));
    expect($prov->isEnabled())->toBeTrue();

    Config::set('lunar.erp.smartbill.enabled', false);
    expect($prov->isEnabled())->toBeFalse();
});

it('getProviderName returns smartbill', function () {
    $prov = new SmartbillErpProvider(\Mockery::mock(ErpApiClientInterface::class));
    expect($prov->getProviderName())->toBe('smartbill');
});

it('getProviderSpecificData returns empty array', function () {
    $prov = new SmartbillErpProvider(\Mockery::mock(ErpApiClientInterface::class));
    expect($prov->getProviderSpecificData([]))->toBe([]);
});

it('getAttributes returns empty array by default', function () {
    $prov = new SmartbillErpProvider(\Mockery::mock(ErpApiClientInterface::class));
    expect($prov->getAttributes())->toBe([]);
});
