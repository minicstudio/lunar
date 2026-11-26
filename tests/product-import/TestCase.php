<?php

namespace Lunar\Tests\ProductImport;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsServiceProvider;
use Livewire\LivewireServiceProvider;
use Lunar\Admin\LunarPanelProvider;
use Lunar\Admin\Models\Staff;
use Lunar\Klaviyo\KlaviyoServiceProvider;
use Lunar\LunarServiceProvider;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Nestedset\NestedSetServiceProvider;
use Lunar\ProductImport\ProductImportServiceProvider;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\ProductImport\Providers\ProductImportPanelTestServiceProvider;
use Lunar\Tests\TestCase as BaseTestCase;
use Maatwebsite\Excel\ExcelServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\Models\Permission;
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
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            BladeLucideIconsServiceProvider::class,
            FilamentApexChartsServiceProvider::class,

            ProductImportServiceProvider::class,
            ProductImportPanelTestServiceProvider::class,

            AiServiceProvider::class,
            ExcelServiceProvider::class,
            KlaviyoServiceProvider::class,
            LaravelDataServiceProvider::class,
            LivewireServiceProvider::class,
            MediaLibraryServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            NestedSetServiceProvider::class,
            BlinkServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', User::class);

        parent::getEnvironmentSetUp($app);
    }

    /**
     * Create the default language and currency used by imports.
     */
    protected function createLanguageAndCurrency(): void
    {
        if (! Language::where('default', true)->exists()) {
            Language::factory()->create(['code' => 'en', 'default' => true]);
        }

        if (! Currency::where('default', true)->exists()) {
            Currency::factory()->create(['default' => true, 'decimal_places' => 2]);
        }
    }

    /**
     * Create a staff member, optionally an admin, holding the given permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function makeStaff(bool $admin = false, array $permissions = []): Staff
    {
        $staff = Staff::factory()->create(['admin' => $admin]);

        foreach ($permissions as $permissionName) {
            $staff->givePermissionTo(Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'staff',
            ]));
        }

        return $staff;
    }
}
