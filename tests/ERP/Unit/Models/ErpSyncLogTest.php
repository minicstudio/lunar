<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\ERP\Models\ErpSyncLog;

test('fillable and casts behave as expected', function () {
    $log = ErpSyncLog::create([
        'provider' => 'magister',
        'sync_type' => 'products',
        'status' => 'pending',
        'started_at' => now(),
        'completed_at' => now()->addMinute(),
        'items_processed' => 5,
        'items_total' => 10,
        'error_message' => null,
        'sync_data' => ['batch' => 1],
    ]);

    expect($log->exists)->toBeTrue()
        ->and($log->sync_data)->toBe(['batch' => 1])
        ->and($log->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($log->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('scopeByProvider filters by provider', function () {
    ErpSyncLog::create(['provider' => 'magister', 'sync_type' => 'products']);
    ErpSyncLog::create(['provider' => 'smartbill', 'sync_type' => 'products']);

    $result = ErpSyncLog::query()->byProvider('magister')->pluck('provider')->all();

    expect($result)->toBe(['magister']);
});

it('scopeByType filters by sync type', function () {
    ErpSyncLog::create(['provider' => 'magister', 'sync_type' => 'products']);
    ErpSyncLog::create(['provider' => 'smartbill', 'sync_type' => 'products']);

    $result = ErpSyncLog::query()->byType('products')->pluck('provider')->all();

    expect($result)->toBe(['magister', 'smartbill']);
});

it('completed and failed scopes filter by status', function () {
    ErpSyncLog::create(['provider' => 'magister', 'sync_type' => 'products', 'status' => 'completed']);
    ErpSyncLog::create(['provider' => 'magister', 'sync_type' => 'products', 'status' => 'failed']);

    $completed = ErpSyncLog::completed()->pluck('status')->unique()->all();
    $failed = ErpSyncLog::failed()->pluck('status')->unique()->all();

    expect($completed)->toBe(['completed'])
        ->and($failed)->toBe(['failed']);
});
