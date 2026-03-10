<?php

uses(\Lunar\Tests\ERP\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Providers\Magister\Requests\ConfirmReceivingDataRequest;
use Lunar\ERP\Providers\Magister\Requests\GetAttributesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetLocalitiesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetModifiedDeliveryOrderRequest;
use Lunar\ERP\Providers\Magister\Requests\GetModifiedStockByShopRequest;
use Lunar\ERP\Providers\Magister\Requests\GetNextModifiedArticlesRequest;
use Lunar\ERP\Providers\Magister\Requests\SendOrderRequest;
use Saloon\Enums\Method;

beforeEach(function () {
    Config::set('lunar.erp.magister.app_id', 'APP');
    Config::set('lunar.erp.magister.shop_id', '1');
});

it('GetNextModifiedArticlesRequest builds endpoint and method', function () {
    $req = new GetNextModifiedArticlesRequest;
    expect($req->resolveEndpoint())->toBe('/GetNextModifiedArticles/APP/1');
    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::GET);
});

it('GetModifiedStockByShopRequest builds endpoint and method', function () {
    $req = new GetModifiedStockByShopRequest;
    expect($req->resolveEndpoint())->toBe('/GetNextModifiedStockByShop/APP');
    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::GET);
});

it('GetModifiedDeliveryOrderRequest builds endpoint and method', function () {
    $req = new GetModifiedDeliveryOrderRequest;
    expect($req->resolveEndpoint())->toBe('/GetModifiedDeliveryOrder/APP');
    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::GET);
});

it('ConfirmReceivingDataRequest builds endpoint and method', function () {
    $req = new ConfirmReceivingDataRequest(101, 5);
    expect($req->resolveEndpoint())->toBe('/%22ConfirmReceivingDataByTypeOf%22/APP/101/1/5');
    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::POST);
});

it('SendOrderRequest builds endpoint, headers, method and body', function () {
    $payload = ['foo' => 'bar'];
    $req = new SendOrderRequest($payload);
    expect($req->resolveEndpoint())->toBe('/%22AddNewDeliveryOrder%22/APP')
        ->and($req->defaultHeaders())->toMatchArray([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->and($req->defaultBody())->toBe($payload);

    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::POST);
});

it('GetLocalitiesRequest / GetAttributesRequest endpoints & method', function () {
    $loc = new GetLocalitiesRequest;
    $attr = new GetAttributesRequest;
    expect($loc->resolveEndpoint())->toBe('/GetAllLocalities/APP/RO')
        ->and($attr->resolveEndpoint())->toBe('/GetAllAttributes/APP');

    $ref = new ReflectionClass($loc);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($loc))->toBe(Method::GET);
    $refA = new ReflectionClass($attr);
    $propA = $refA->getProperty('method');
    $propA->setAccessible(true);
    expect($propA->getValue($attr))->toBe(Method::GET);
});
