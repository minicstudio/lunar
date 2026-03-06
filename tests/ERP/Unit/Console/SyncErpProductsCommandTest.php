<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Console\SyncErpProductsCommand;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;

test('skips when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $command = new SyncErpProductsCommand;

    $this->artisan($command)
        ->expectsOutput('ERP sync is not enabled, skipping sync.')
        ->assertExitCode(0);
});

test('skips when no ERP providers are enabled', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'products')
            ->andReturn([]);
    });

    $command = new SyncErpProductsCommand;

    $this->artisan($command)
        ->expectsOutput('No ERP providers are enabled.')
        ->assertExitCode(0);
});

test('syncs products successfully and reports counts', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'products')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncProducts')
            ->once()
            ->with(ErpProviderEnum::magister, \Mockery::on(function ($cb) {
                return is_callable($cb);
            }))
            ->andReturn([
                'success' => true,
                'products_processed' => 7,
            ]);
    });

    $command = new SyncErpProductsCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing products from magister. Wait for progress...')
        ->expectsOutput('✓ magister: 7 products synced successfully')
        ->assertExitCode(0);
});

test('warns when product sync completes with warnings and exercises progress', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'products')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncProducts')
            ->once()
            ->with(ErpProviderEnum::magister, \Mockery::on(function ($cb) {
                $cb(0, 3, 'start');
                $cb(1, 3, 'step1');
                $cb(3, 3, 'done');

                return is_callable($cb);
            }))
            ->andReturn([
                'success' => false,
                'products_processed' => 3,
                'message' => 'partial',
            ]);
    });

    $command = new SyncErpProductsCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing products from magister. Wait for progress...')
        ->expectsOutput('⚠ magister: Sync completed with warnings - partial')
        ->assertExitCode(0);
});

test('handles exception during product sync gracefully', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'products')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncProducts')
            ->once()
            ->with(ErpProviderEnum::magister, \Mockery::on(function ($cb) {
                return is_callable($cb);
            }))
            ->andThrow(new Exception('not allowed'));
    });

    $command = new SyncErpProductsCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing products from magister. Wait for progress...')
        ->expectsOutput('✗ magister: Product sync failed - not allowed')
        ->assertExitCode(0);
});

test('fails when an invalid ERP provider is selected for products', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'products')
            ->andReturn([ErpProviderEnum::magister]);
    });

    $command = new SyncErpProductsCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'invalid', ['magister' => 'Magister'])
        ->expectsOutput('Invalid ERP provider: invalid')
        ->assertExitCode(1);
});
