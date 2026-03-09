<?php

namespace Lunar\Tests\Review;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Cartalyst\Converter\Laravel\ConverterServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Livewire\LivewireServiceProvider;
use Lunar\Admin\LunarPanelProvider;
use Lunar\Admin\Models\Staff;
use Lunar\LunarServiceProvider;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Review\ReviewServiceProvider;
use Lunar\Tests\review\Providers\ReviewPanelTestServiceProvider;
use Lunar\Tests\TestCase as BaseTestCase;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Technikermathe\LucideIcons\BladeLucideIconsServiceProvider;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        activity()->disableLogging();

        $this->freezeTime();
    }

    protected function getPackageProviders($app)
    {
        return [
            LunarServiceProvider::class,
            LunarPanelProvider::class,

            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            BladeLucideIconsServiceProvider::class,

            ReviewServiceProvider::class,
            ReviewPanelTestServiceProvider::class,

            LivewireServiceProvider::class,
            MediaLibraryServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            ConverterServiceProvider::class,
            NestedSetServiceProvider::class,
            BlinkServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');

        $this->replaceModelsForTesting();
    }

    protected function createLanguages()
    {
        $defaultLanguage = Language::where('code', 'en')->first();

        if (! $defaultLanguage) {
            $defaultLanguage = Language::factory()->create([
                'code' => 'en',
            ]);
        }

        $nonDefaultLanguage = Language::where('default', false)->first();

        if (! $nonDefaultLanguage) {
            Language::factory()->create([
                'default' => false,
                'code' => 'hu',
                'name' => 'Magyar',
            ]);
        }
    }

    /**
     * Create default and non-default currencies if they do not exist.
     */
    protected function createCurrencies(): void
    {
        $defaultCurrency = Currency::where('code', 'EUR')->first();

        if (! $defaultCurrency) {
            $defaultCurrency = Currency::factory()->create([
                'code' => 'EUR',
            ]);
        }

        $nonDefaultCurrency = Currency::where('default', false)->first();

        if (! $nonDefaultCurrency) {
            Currency::factory()->create([
                'code' => 'RON',
                'default' => false,
            ]);
        }
    }

    protected function asStaff($admin = true): TestCase
    {
        return $this->actingAs($this->makeStaff($admin), 'staff');
    }

    protected function makeStaff($admin = true): Staff
    {
        $staff = Staff::factory()->create([
            'admin' => $admin,
        ]);

        $roleName = $admin ? 'admin' : 'staff';
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'staff',
        ]);

        if ($admin) {
            $permissions = [
                'sales:reviews:manage',
            ];

            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'staff',
                ]);

                $role->givePermissionTo($permission);
            }
        }

        $staff->assignRole($role);

        return $staff;
    }
}
