<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;

test('skips when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $this->artisan('erp:sync-stock')
        ->expectsOutput('ERP sync is not enabled, skipping sync.')
        ->assertExitCode(0);
});

test('skips when no ERP providers are enabled', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'stock')
            ->andReturn([]);
    });

    $this->artisan('erp:sync-stock')
        ->expectsOutput('No ERP providers are enabled.')
        ->assertExitCode(0);
});

test('syncs stock successfully and reports counts', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'stock')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncStock')
            ->once()
            ->with(ErpProviderEnum::magister, \Mockery::on(function ($cb) {
                return is_callable($cb);
            }))
            ->andReturn([
                'success' => true,
                'stock_items_processed' => 5,
            ]);
    });

    $this->artisan('erp:sync-stock')
        ->expectsChoice('Which ERP provider would you like to sync stock from?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing stock from magister...')
        ->expectsOutput('✓ magister: 5 stock items synced successfully')
        ->assertExitCode(0);
});

test('warns when stock sync completes with warnings', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'stock')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncStock')
            ->once()
            ->with(ErpProviderEnum::magister, \Mockery::on(function ($cb) {
                $cb(0, 2, 'start');
                $cb(2, 2, 'done');

                return is_callable($cb);
            }))
            ->andReturn([
                'success' => false,
                'stock_items_processed' => 2,
                'message' => 'partial',
            ]);
    });

    $this->artisan('erp:sync-stock')
        ->expectsChoice('Which ERP provider would you like to sync stock from?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing stock from magister...')
        ->expectsOutput('⚠ magister: Sync completed with warnings - partial')
        ->assertExitCode(0);
});

test('handles exception during stock sync gracefully', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'stock')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('syncStock')
            ->once()
            ->with(ErpProviderEnum::magister, \Mockery::on(function ($cb) {
                return is_callable($cb);
            }))
            ->andThrow(new Exception('not allowed'));
    });

    $this->artisan('erp:sync-stock')
        ->expectsChoice('Which ERP provider would you like to sync stock from?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Syncing stock from magister...')
        ->expectsOutput('✗ magister: Stock sync failed - not allowed')
        ->assertExitCode(0);
});

test('fails when an invalid ERP provider is selected for stock', function () {
    Config::set('lunar.erp.enabled', true);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'stock')
            ->andReturn([ErpProviderEnum::magister]);
    });

    $this->artisan('erp:sync-stock')
        ->expectsChoice('Which ERP provider would you like to sync stock from?', 'invalid', ['magister' => 'Magister'])
        ->expectsOutput('Invalid ERP provider: invalid')
        ->assertExitCode(1);
});
