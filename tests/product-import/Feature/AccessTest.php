<?php

use Filament\Facades\Filament;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use Lunar\Admin\Support\DataTransferObjects\Permission as PanelPermission;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\ProductImport\Database\State\EnsureProductImportPermissions;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ListProductImports;
use Lunar\Tests\ProductImport\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();

    Filament::setCurrentPanel(Filament::getPanel('lunar'));
});

test('the permission exists after migrating', function () {
    Permission::query()->where('name', 'catalog:import-products')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    event(new NoPendingMigrations('up'));

    expect(Permission::query()->where('name', 'catalog:import-products')->where('guard_name', 'staff')->count())->toBe(1);

    (new EnsureProductImportPermissions)->run();

    expect(Permission::query()->where('name', 'catalog:import-products')->count())->toBe(1);
});

test('staff without the permission see no import button and are refused on the resource', function () {
    $this->actingAs($this->makeStaff(permissions: ['catalog:manage-products']), 'staff');

    Livewire::test(ListProducts::class)
        ->assertActionDoesNotExist('importProducts');

    expect(ProductImportResource::canAccess())->toBeFalse();

    $this->get(ProductImportResource::getUrl('index'))->assertForbidden();
});

test('staff with the permission see the import button and the list', function (bool $admin, array $permissions) {
    $this->actingAs($this->makeStaff(admin: $admin, permissions: $permissions), 'staff');

    Livewire::test(ListProducts::class)
        ->assertActionVisible('importProducts');

    expect(ProductImportResource::canAccess())->toBeTrue();

    $this->get(ProductImportResource::getUrl('index'))->assertOk();

    Livewire::test(ListProductImports::class)->assertOk();
})->with([
    'staff with catalog:import-products' => [false, ['catalog:manage-products', 'catalog:import-products']],
    'admin without explicit permission' => [true, []],
]);

test('the imports item sits in the catalog navigation group', function () {
    expect(ProductImportResource::getNavigationGroup())->toBe('Catalog')
        ->and(ProductImportResource::getPluralLabel())->toBe('Imports');
});

test('the permission label resolves in every admin locale', function (string $locale, string $label) {
    app()->setLocale($locale);

    $permission = PanelPermission::make('catalog:import-products', false);

    expect($permission->transLabel())->toBe($label)
        ->and($permission->transDescription())->not->toBe('catalog:import-products');
})->with([
    'en' => ['en', 'Import products'],
    'hu' => ['hu', 'Termékek importálása'],
    'ro' => ['ro', 'Importare produse'],
]);

test('the english description reads as specified', function () {
    app()->setLocale('en');

    expect(PanelPermission::make('catalog:import-products', false)->transDescription())
        ->toBe('Allow the staff member to import products from Excel and CSV files');
});

test('admin permission labels still resolve after the merge', function () {
    app()->setLocale('en');

    expect(__('lunarpanel::auth.permissions.settings.label'))->not->toBe('lunarpanel::auth.permissions.settings.label');
});

test('without the plugin the product list has no import action', function () {
    // A panel without the import plugin: a fresh panel manager holds no import extensions.
    app()->forgetScopedInstances();
    LunarPanel::clearResolvedInstance('lunar-panel');

    $this->actingAs($this->makeStaff(admin: true), 'staff');

    expect(LunarPanel::getExtensions())->not->toHaveKey(ListProducts::class);

    Livewire::test(ListProducts::class)
        ->assertActionDoesNotExist('importProducts');
});
