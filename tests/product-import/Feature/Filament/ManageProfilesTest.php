<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ManageProfiles;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
});

test('profiles are listed with their name, header count, usage, and last use', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    ProductImportProfile::factory()->create([
        'name' => 'Furnizor Textil SRL export',
        'headers' => ['cod', 'denumire', 'pret'],
        'usage_count' => 4,
        'last_used_at' => now(),
    ]);

    Livewire::test(ManageProfiles::class)
        ->assertSee('Furnizor Textil SRL export')
        ->assertSee('Import profiles')
        ->assertCanSeeTableRecords(ProductImportProfile::all());
});

test('a profile can be renamed and deleted', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $profile = ProductImportProfile::factory()->create(['name' => 'Vechi']);
    ProductImportProfile::factory()->create(['name' => 'Ocupat']);

    Livewire::test(ManageProfiles::class)
        ->callTableAction('rename', $profile, data: ['name' => 'Ocupat'])
        ->assertNotified('Another profile already has this name.');

    expect($profile->fresh()->name)->toBe('Vechi');

    Livewire::test(ManageProfiles::class)
        ->callTableAction('rename', $profile, data: ['name' => 'Nou'])
        ->assertHasNoTableActionErrors();

    expect($profile->fresh()->name)->toBe('Nou');

    Livewire::test(ManageProfiles::class)
        ->callTableAction('delete', $profile);

    expect(ProductImportProfile::find($profile->id))->toBeNull();
});

test('staff without the import permission cannot open the profiles', function () {
    $this->actingAs($this->makeStaff(permissions: ['catalog:manage-products']), 'staff');

    $this->get(ProductImportResource::getUrl('profiles'))->assertForbidden();
});
