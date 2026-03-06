<?php

uses(\Lunar\Tests\ERP\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Exceptions\ErpSyncException;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpProvider;
use Lunar\ERP\Providers\Magister\Requests\GetAttributesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetLocalitiesRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Config::set('lunar.erp.magister.base_url', 'https://magister.test');
    Config::set('lunar.erp.magister.app_id', 'APP');
    Config::set('lunar.erp.magister.shop_id', 1);
});

it('isEnabled reads magister flag', function () {
    Config::set('lunar.erp.magister.enabled', true);
    $prov = new MagisterErpProvider(new MagisterApiClient);
    expect($prov->isEnabled())->toBeTrue();
});

it('getProviderName returns magister', function () {
    $prov = new MagisterErpProvider(new MagisterApiClient);
    expect($prov->getProviderName())->toBe('magister');
});

it('getLocalities transforms response to expected shape', function () {
    $mock = new MockClient([
        GetLocalitiesRequest::class => MockResponse::make([
            'result' => [[
                'DATASET' => [
                    ['COUNTY_CODE' => 'CJ', 'COUNTY' => 'Cluj', 'TOWN' => 'Cluj-Napoca'],
                ],
            ]],
        ], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);

    expect($prov->getLocalities())->toBe([
        ['countyCode' => 'CJ', 'countyName' => 'Cluj', 'localityName' => 'Cluj-Napoca'],
    ]);
});

it('getAttributes transforms response to expected shape', function () {
    $mock = new MockClient([
        GetAttributesRequest::class => MockResponse::make([
            'result' => [[
                'DATASET' => [
                    ['NAME' => 'Color', 'ITEMS' => [['NAME' => 'Red'], ['NAME' => 'Blue']]],
                ],
            ]],
        ], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);
    expect($prov->getAttributes())->toBe([
        ['optionName' => 'Color', 'optionValues' => ['Red', 'Blue']],
    ]);
});

it('getProviderSpecificData maps ERP raw data and nulls missing keys', function () {
    $prov = new MagisterErpProvider(new MagisterApiClient);

    $raw = [
        'ARTICLE_KIND' => 'variant',
        'IDSMARTCASH_GENERIC_ARTICLE' => 123,
        'RECVERSION' => 456,
    ];

    expect($prov->getProviderSpecificData($raw))->toBe([
        'article_kind' => 'variant',
        'generic_article_id' => 123,
        'recversion' => 456,
    ]);

    expect($prov->getProviderSpecificData([]))->toBe([
        'article_kind' => null,
        'generic_article_id' => null,
        'recversion' => null,
    ]);
});

it('getLocalities returns empty array on empty or missing dataset', function () {
    $mock = new MockClient([
        GetLocalitiesRequest::class => MockResponse::make([
            'result' => [[]],
        ], 200),
    ]);

    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);

    expect($prov->getLocalities())->toBe([]);
});

it('getLocalities throws ErpSyncException when client fails', function () {
    $mock = new MockClient([
        GetLocalitiesRequest::class => MockResponse::make(['error' => 'fail'], 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);

    expect(fn () => $prov->getLocalities())->toThrow(ErpSyncException::class);
});

it('getAttributes returns empty array on empty or missing dataset', function () {
    $mock = new MockClient([
        GetAttributesRequest::class => MockResponse::make([
            'result' => [[]],
        ], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);

    expect($prov->getAttributes())->toBe([]);
});

it('getAttributes handles empty ITEMS', function () {
    $mock = new MockClient([
        GetAttributesRequest::class => MockResponse::make([
            'result' => [[
                'DATASET' => [
                    ['NAME' => 'Size', 'ITEMS' => []],
                ],
            ]],
        ], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);

    expect($prov->getAttributes())->toBe([
        ['optionName' => 'Size', 'optionValues' => []],
    ]);
});

it('getAttributes throws ErpSyncException when client fails', function () {
    $mock = new MockClient([
        GetAttributesRequest::class => MockResponse::make(['error' => 'fail'], 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $prov = new MagisterErpProvider($api);

    expect(fn () => $prov->getAttributes())->toThrow(ErpSyncException::class);
});
