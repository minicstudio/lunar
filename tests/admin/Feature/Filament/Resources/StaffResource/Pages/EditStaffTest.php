<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\StaffResource;
use Lunar\Admin\Filament\Resources\StaffResource\Pages\EditStaff;
use Lunar\Admin\Models\Staff;
use Lunar\Admin\Support\Facades\LunarAccessControl;
use Lunar\Tests\Admin\Feature\Filament\TestCase;
use Spatie\Permission\Models\Role;

uses(TestCase::class)
    ->group('resource.staff');

beforeEach(fn () => $this->asStaff(admin: true));

it('can render staff edit page', function () {
    $this->get(StaffResource::getUrl('edit', ['record' => Staff::factory()->create()]))
        ->assertSuccessful();
});

it('can retrieve staff data', function () {
    $staff = Staff::factory()->create();

    Livewire::test(EditStaff::class, [
        'record' => $staff->getRouteKey(),
    ])
        ->assertFormSet([
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'email' => $staff->email,
        ]);
});

it('can save staff data', function () {
    $staff = Staff::factory()->create();

    $newData = Staff::factory()->make();

    Livewire::test(EditStaff::class, [
        'record' => $staff->getRouteKey(),
    ])
        ->fillForm([
            'first_name' => $newData->first_name,
            'last_name' => $newData->last_name,
            'email' => $newData->email,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staff->refresh())
        ->first_name->toBe($newData->first_name)
        ->last_name->toBe($newData->last_name)
        ->email->toBe($newData->email);
});

it('does not overwrite password when editing staff without a new password', function () {
    $plainPassword = 'OriginalPass123!';

    $staff = Staff::factory()->create([
        'password' => $plainPassword,
        'admin' => false,
    ]);

    $passwordBefore = $staff->getAttributes()['password'];

    Livewire::test(EditStaff::class, [
        'record' => $staff->getRouteKey(),
    ])
        ->fillForm([
            'first_name' => 'FirstName',
            'last_name' => 'Editor',
            'email' => $staff->email,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();

    expect($staff->getAttributes()['password'])->toBe($passwordBefore)
        ->and(Hash::check($plainPassword, $staff->password))->toBeTrue()
        ->and($staff->first_name)->toBe('FirstName');
});

it('can update staff password when a new value is provided', function () {
    $staff = Staff::factory()->create([
        'password' => 'OriginalPass123!',
        'admin' => false,
    ]);

    Livewire::test(EditStaff::class, [
        'record' => $staff->getRouteKey(),
    ])
        ->set('data.password', 'ReplacementPass123!')
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();

    expect(Hash::check('ReplacementPass123!', $staff->password))->toBeTrue()
        ->and(Hash::check('OriginalPass123!', $staff->password))->toBeFalse();
});

it('can assign staff role and permissions', function () {
    $staff = Staff::factory()->create([
        'admin' => false,
    ]);

    $roles = ['staff'];
    $permissions = LunarAccessControl::getGroupedPermissions()->random(4)->mapWithKeys(fn ($perm) => [$perm->handle => true]);
    $rolePermission = array_keys($permissions->take(1)->toArray());

    $staffRole = Role::findByName('staff');
    $staffRole->syncPermissions($rolePermission);

    Livewire::test(EditStaff::class, [
        'record' => $staff->getRouteKey(),
    ])
        ->fillForm([
            'roles' => $roles,
            'permissions' => $permissions->toArray(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staff->hasExactRoles($roles))
        ->toBeTrue()
        ->and(
            $permissions->reject(fn ($val, $handle) => $handle == $rolePermission)->keys()->toArray()
        )->toEqualCanonicalizing($staff->getDirectPermissions()->pluck('name')->toArray())
        ->and($rolePermission)
        ->toEqualCanonicalizing($staff->getPermissionsViaRoles()->pluck('name')->toArray());
});
