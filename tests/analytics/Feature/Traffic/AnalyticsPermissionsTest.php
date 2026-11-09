<?php

use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Support\DataTransferObjects\Permission as PanelPermission;
use Lunar\Analytics\Database\State\EnsureAnalyticsPermissions;
use Lunar\Tests\Analytics\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::query()->where('name', 'analytics:traffic-sources')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('run creates the traffic sources permission on the staff guard', function () {
    (new EnsureAnalyticsPermissions)->run();

    expect(Permission::query()->where('name', 'analytics:traffic-sources')->where('guard_name', 'staff')->count())->toBe(1);
});

test('the permission is created when migrations finish', function () {
    event(new NoPendingMigrations('up'));

    expect(Permission::query()->where('name', 'analytics:traffic-sources')->where('guard_name', 'staff')->exists())->toBeTrue();
});

test('a second run leaves one permission and changes no role', function () {
    $roles = Role::count();

    (new EnsureAnalyticsPermissions)->run();
    (new EnsureAnalyticsPermissions)->run();

    expect(Permission::query()->where('name', 'analytics:traffic-sources')->count())->toBe(1)
        ->and(Role::count())->toBe($roles);
});

test('the permission label and description resolve in every admin locale', function (string $locale, string $label) {
    app()->setLocale($locale);

    $permission = PanelPermission::make('analytics:traffic-sources', false);

    expect($permission->transLabel())->toBe($label)
        ->and($permission->transDescription())->not->toBe('analytics:traffic-sources');
})->with([
    'en' => ['en', 'View Traffic Sources'],
    'hu' => ['hu', 'Forgalmi források megtekintése'],
    'ro' => ['ro', 'Vizualizare surse de trafic'],
]);

test('admin permission labels still resolve after the merge', function () {
    app()->setLocale('en');

    expect(__('lunarpanel::auth.permissions.settings.label'))->not->toBe('lunarpanel::auth.permissions.settings.label');
});
