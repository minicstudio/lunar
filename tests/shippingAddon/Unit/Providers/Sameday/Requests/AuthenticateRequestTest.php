<?php

uses(\Lunar\Tests\ShippingAddon\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\AuthenticateRequest;
use Saloon\Enums\Method;

beforeEach(function () {
    Config::set('lunar.shipping.sameday.username', 'user_test');
    Config::set('lunar.shipping.sameday.password', 'pass_test');
});

it('builds correct headers from config', function () {
    $req = new AuthenticateRequest;
    $headers = $req->defaultHeaders();

    expect($headers)->toMatchArray([
        'X-AUTH-USERNAME' => 'user_test',
        'X-AUTH-PASSWORD' => 'pass_test',
        'Accept' => 'application/json',
        'Content-Type' => 'application/x-www-form-urlencoded',
    ]);
});

it('builds correct body', function () {
    $req = new AuthenticateRequest;
    expect($req->defaultBody())->toBe(['remember_me' => 'true']);
});

it('has correct endpoint and method', function () {
    $req = new AuthenticateRequest;
    expect($req->resolveEndpoint())->toBe('/api/authenticate');

    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    $method = $prop->getValue($req);
    expect($method)->toBe(Method::POST);
});
