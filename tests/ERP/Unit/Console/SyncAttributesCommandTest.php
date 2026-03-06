<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Lunar\ERP\Console\SyncAttributesCommand;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\ProductOption;

test('fails when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $command = new SyncAttributesCommand;

    $this->artisan($command)
        ->expectsOutput('ERP sync is disabled in configuration.')
        ->assertExitCode(1);
});

test('skips when no ERP providers are enabled', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'attributes')
            ->andReturn([]);
    });

    $command = new SyncAttributesCommand;

    $this->artisan($command)
        ->expectsOutput('No ERP providers are enabled.')
        ->assertExitCode(0);
});

test('handles empty attributes response', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'attributes')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('getAttributes')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn([]);
    });

    $command = new SyncAttributesCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Starting attribute sync from ERP...')
        ->expectsOutput('Fetching attributes from ERP...')
        ->expectsOutput('No attributes found in ERP response.')
        ->assertExitCode(0);
});

test('creates attributes and reports counts', function () {
    Config::set('lunar.erp.enabled', true);

    $payload = [
        [
            'optionName' => 'Color',
            'optionValues' => ['Red', 'Green'],
        ],
        [
            'optionName' => 'Size',
            'optionValues' => ['S', 'M', 'L'],
        ],
    ];

    $this->mock(ErpService::class, function ($mock) use ($payload) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'attributes')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('getAttributes')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn($payload);
    });

    $command = new SyncAttributesCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Starting attribute sync from ERP...')
        ->expectsOutput('Fetching attributes from ERP...')
        ->expectsOutput('Found '.count($payload).' attributes to process.')
        ->expectsOutput('Attribute sync completed successfully!')
        ->expectsOutput('Created 2 new attributes as product options.')
        ->assertExitCode(0);

    expect(ProductOption::count())->toBe(2);

    $color = ProductOption::where('handle', Str::slug('Color'))->first();
    expect($color)->not->toBeNull();
    expect($color->values()->count())->toBe(2);

    $size = ProductOption::where('handle', Str::slug('Size'))->first();
    expect($size)->not->toBeNull();
    expect($size->values()->count())->toBe(3);
});

test('fails when processing attributes throws an exception', function () {
    Config::set('lunar.erp.enabled', true);

    $payload = [
        [
            'optionName' => 'Material',
            'optionValues' => ['Cotton'],
        ],
    ];

    $this->mock(ErpService::class, function ($mock) use ($payload) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'attributes')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('getAttributes')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn($payload);
    });

    ProductOption::saving(function ($model) {
        if ($model->handle === Str::slug('Material')) {
            throw new Exception('db error');
        }
    });

    $command = new SyncAttributesCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Starting attribute sync from ERP...')
        ->expectsOutput('Fetching attributes from ERP...')
        ->expectsOutput('Failed to sync attributes: db error')
        ->assertExitCode(1);

    ProductOption::flushEventListeners();
});
