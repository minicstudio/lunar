<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\CustomerGroup;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Channel;
use Lunar\Models\Currency;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can make a collection', function () {
    $collection = Collection::factory()
        ->create([
            'attribute_data' => collect([
                'name' => new Text('Red Products'),
            ]),
        ]);

    expect('Red Products')->toEqual($collection->translateAttribute('name'));
});


test('scopeAvailableCustomerGroups returns collections matching session customer groups and visibility rules', function () {
    $channel = Channel::where('default', true)->first();

    if (! $channel) {
        $channel = Channel::factory()->create([
            'default' => true,
        ]);
    }

    $customerGroup = CustomerGroup::where('default', true)->first();

    if (! $customerGroup) {
        $customerGroup = CustomerGroup::factory()->create([
            'default' => true,
        ]);
    }

    $currency = Currency::where('code', 'EUR')->first();

    if (! $currency) {
        $currency = Currency::factory()->create([
            'code' => 'EUR',
        ]);
    }

    $collectionGroup = CollectionGroup::factory()->create();

    $validCustomerGroup = CustomerGroup::factory()->create(['default' => false]);
    $invalidCustomerGroup = CustomerGroup::factory()->create(['default' => false]);

    StorefrontSession::setCustomerGroups(collect([$validCustomerGroup]));

    $match = Collection::factory()->create([
        'collection_group_id' => $collectionGroup->id,
    ]);
    $noMatch = Collection::factory()->create([
        'collection_group_id' => $collectionGroup->id,
    ]);

    $match->customerGroups()->sync([
        $validCustomerGroup->id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => null,
            'ends_at' => null,
        ],
    ]);

    $noMatch->customerGroups()->sync([
        $invalidCustomerGroup->id => [
            'enabled' => true,
            'visible' => false,
            'starts_at' => null,
            'ends_at' => null,
        ],
    ]);

    $results = Collection::availableCustomerGroups()->pluck('id')->all();

    expect($results)->toContain($match->id)
        ->and($results)->not->toContain($noMatch->id);
});
