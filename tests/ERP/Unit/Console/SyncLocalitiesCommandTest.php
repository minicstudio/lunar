<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Console\SyncLocalitiesCommand;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;
use Lunar\Locations\Models\County;
use Lunar\Locations\Models\Locality;
use Lunar\Models\Country;

test('fails when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $command = new SyncLocalitiesCommand;

    $this->artisan($command)
        ->expectsOutput('ERP sync is disabled in configuration.')
        ->assertExitCode(1);
});

test('fails when Romania country is missing', function () {
    Config::set('lunar.erp.enabled', true);

    Country::where('iso2', 'RO')->delete();

    $command = new SyncLocalitiesCommand;

    $this->artisan($command)
        ->expectsOutput('Romania country not found. Please ensure countries are seeded.')
        ->assertExitCode(1);
});

test('skips when no ERP providers are enabled', function () {
    Config::set('lunar.erp.enabled', true);

    Country::factory()->create(['iso2' => 'RO']);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'localities')
            ->andReturn([]);
    });

    $command = new SyncLocalitiesCommand;

    $this->artisan($command)
        ->expectsOutput('No ERP providers are enabled.')
        ->assertExitCode(0);
});

test('handles empty localities response', function () {
    Config::set('lunar.erp.enabled', true);

    Country::factory()->create(['iso2' => 'RO']);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'localities')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('getLocalities')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn([]);
    });

    $command = new SyncLocalitiesCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Starting localities sync from ERP...')
        ->expectsOutput('Fetching localities from ERP...')
        ->expectsOutput('No localities found in ERP response.')
        ->assertExitCode(0);
});

test('creates counties and localities and reports counts', function () {
    Config::set('lunar.erp.enabled', true);

    $ro = Country::factory()->create(['iso2' => 'RO']);

    $payload = [
        ['countyCode' => 'CJ', 'countyName' => 'Cluj', 'localityName' => 'Cluj-Napoca'],
        ['countyCode' => 'CJ', 'countyName' => 'Cluj', 'localityName' => 'Floresti'],
        ['countyCode' => 'B',  'countyName' => 'Bucuresti', 'localityName' => 'Sector 1'],
        ['countyCode' => 'CJ', 'countyName' => 'Cluj', 'localityName' => 'Cluj-Napoca'],
    ];

    $this->mock(ErpService::class, function ($mock) use ($payload) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'localities')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('getLocalities')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn($payload);
    });

    $command = new SyncLocalitiesCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Starting localities sync from ERP...')
        ->expectsOutput('Fetching localities from ERP...')
        ->expectsOutput('Found 4 localities to process.')
        ->expectsOutput('Localities sync completed successfully!')
        ->expectsOutput('Created 2 new counties and 3 new localities.')
        ->assertExitCode(0);

    expect(County::count())->toBe(2);
    expect(Locality::count())->toBe(3);

    $cluj = County::where('code', 'CJ')->first();
    expect($cluj)->not->toBeNull();
    expect($cluj->country_id)->toBe($ro->id);
    expect($cluj->localities()->count())->toBe(2);
    expect(Locality::where('name', 'Cluj-Napoca')->where('county_id', $cluj->id)->exists())->toBeTrue();
});

test('fails when processing throws an exception', function () {
    Config::set('lunar.erp.enabled', true);

    Country::factory()->create(['iso2' => 'RO']);

    $this->mock(ErpService::class, function ($mock) {
        $mock->shouldReceive('getAllowedProviders')
            ->once()
            ->with('sync', 'localities')
            ->andReturn([ErpProviderEnum::magister]);

        $mock->shouldReceive('getLocalities')
            ->once()
            ->with(ErpProviderEnum::magister)
            ->andReturn([
                ['countyCode' => 'X', 'countyName' => 'X', 'localityName' => 'Y'],
            ]);
    });

    County::saving(function ($model) {
        if ($model->code === 'X') {
            throw new Exception('db error');
        }
    });

    $command = new SyncLocalitiesCommand;

    $this->artisan($command)
        ->expectsChoice('Which ERP provider would you like to sync?', 'magister', ['magister' => 'Magister'])
        ->expectsOutput('Starting localities sync from ERP...')
        ->expectsOutput('Fetching localities from ERP...')
        ->expectsOutput('Failed to sync localities: db error')
        ->assertExitCode(1);

    County::flushEventListeners();
});
