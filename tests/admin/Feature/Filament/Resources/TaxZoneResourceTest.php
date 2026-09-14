<?php

use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\TaxZoneResource\Pages\EditTaxZone;
use Lunar\Models\Country;
use Lunar\Models\State;
use Lunar\Models\TaxZone;
use Lunar\Tests\Admin\TestCase;

uses(TestCase::class)->group('resource.taxZone');

it('retains the selected country after saving and reopening a tax zone', function (string $zoneType) {
    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);
    $taxZone = TaxZone::factory()->create(['zone_type' => $zoneType]);

    $data = match ($zoneType) {
        'country' => ['zone_countries' => [$country->iso3]],
        'states' => ['zone_country' => $country->id, 'zone_states' => [$state->code]],
        'postcodes' => ['zone_country' => $country->id, 'zone_postcodes' => '12345'],
    };

    Livewire::actingAs($this->makeStaff(admin: true), 'staff')
        ->test(EditTaxZone::class, ['record' => $taxZone->getRouteKey()])
        ->fillForm($data)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($taxZone->countries()->pluck('country_id')->all())->toBe([$country->id]);

    Livewire::test(EditTaxZone::class, ['record' => $taxZone->getRouteKey()])
        ->assertFormSet($data);
})->with(['country', 'states', 'postcodes']);
