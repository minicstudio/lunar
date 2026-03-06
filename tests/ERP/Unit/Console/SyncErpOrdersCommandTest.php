<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Console\SyncErpOrdersCommand;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;

test('skips when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $command = new SyncErpOrdersCommand;

    $this->artisan($command)
        ->expectsOutput('ERP sync is not enabled, skipping sync.')
        ->assertExitCode(0);
});

test('skips when no ERP providers are enabled', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'orders')
            ->andReturn([]);
    });

    $command = new SyncErpOrdersCommand;

    $this->artisan($command)
        ->expectsOutput('No ERP providers are enabled.')
        ->assertExitCode(0);
});

test('syncs orders successfully and reports counts', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'orders')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncOrderStatuses')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn([
                'success' => true,
                'orders_processed' => 3,
            ]);
    });

    $command = new SyncErpOrdersCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync order statuses from?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing orders to magister...')
        ->expectsOutput('✓ magister: 3 orders synced successfully')
        ->assertExitCode(0);
});

test('warns when sync completes with warnings', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'orders')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncOrderStatuses')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn([
                'success' => false,
                'orders_processed' => 2,
                'message' => 'partial',
            ]);
    });

    $command = new SyncErpOrdersCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync order statuses from?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing orders to magister...')
        ->expectsOutput('⚠ magister: Sync completed with warnings - partial')
        ->assertExitCode(0);
});

test('handles exception during order sync gracefully', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'orders')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncOrderStatuses')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andThrow(new Exception('not allowed'));
    });

    $command = new SyncErpOrdersCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync order statuses from?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing orders to magister...')
        ->expectsOutput('✗ magister: Order sync failed - not allowed')
        ->assertExitCode(0);
});

test('fails when an invalid ERP provider is selected', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'orders')
            ->andReturn([ErpProviderEnum::magister]);
    });

    $command = new SyncErpOrdersCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync order statuses from?', 'invalid', ['magister' => 'Magister'])
        ->expectsOutput('Invalid ERP provider: invalid')
        ->assertExitCode(1);
});
