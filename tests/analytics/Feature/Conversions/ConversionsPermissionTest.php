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
    Permission::query()->whereIn('name', ['analytics:traffic-sources', 'analytics:conversions'])->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('run creates the conversions permission on the staff guard and keeps the traffic permission', function () {
    (new EnsureAnalyticsPermissions)->run();

    expect(Permission::query()->where('name', 'analytics:conversions')->where('guard_name', 'staff')->count())->toBe(1)
        ->and(Permission::query()->where('name', 'analytics:traffic-sources')->where('guard_name', 'staff')->count())->toBe(1);
});

test('the conversions permission is created when migrations finish', function () {
    event(new NoPendingMigrations('up'));

    expect(Permission::query()->where('name', 'analytics:conversions')->where('guard_name', 'staff')->exists())->toBeTrue();
});

test('a second run leaves one conversions permission and changes no role', function () {
    $roles = Role::count();

    (new EnsureAnalyticsPermissions)->run();
    (new EnsureAnalyticsPermissions)->run();

    expect(Permission::query()->where('name', 'analytics:conversions')->count())->toBe(1)
        ->and(Role::count())->toBe($roles);
});

test('the conversions permission label and description resolve in every admin locale', function (string $locale, string $label) {
    app()->setLocale($locale);

    $permission = PanelPermission::make('analytics:conversions', false);

    expect($permission->transLabel())->toBe($label)
        ->and($permission->transDescription())->not->toBe('analytics:conversions');
})->with([
    'en' => ['en', 'View Conversions'],
    'hu' => ['hu', 'Konverziók megtekintése'],
    'ro' => ['ro', 'Vizualizare conversii'],
]);
