<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\ERP\Models\ErpSyncTemp;

test('can create temp record with casts applied', function () {
    $temp = ErpSyncTemp::create([
        'erp_id' => 'ERP-123',
        'name' => 'Sample',
        'sku' => 'SKU-1',
        'price' => 1234,
        'discount' => 10,
        'category_1' => 'Cat A',
        'category_2' => 'Cat B',
        'provider_data' => ['source' => 'magister'],
        'attributes' => ['color' => 'red'],
    ]);

    expect($temp->exists)->toBeTrue()
        ->and($temp->provider_data)->toBe(['source' => 'magister'])
        ->and($temp->attributes)->toBe(['color' => 'red']);
});
