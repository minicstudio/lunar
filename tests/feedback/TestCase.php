<?php

namespace Lunar\Tests\Feedback;

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
use Livewire\LivewireServiceProvider;
use Lunar\Admin\LunarPanelProvider;
use Lunar\Admin\Models\Staff;
use Lunar\Feedback\FeedbackServiceProvider;
use Lunar\LunarServiceProvider;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Nestedset\NestedSetServiceProvider;
use Lunar\Tests\Feedback\Providers\FeedbackPanelTestServiceProvider;
use Lunar\Tests\TestCase as BaseTestCase;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Technikermathe\LucideIcons\BladeLucideIconsServiceProvider;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        activity()->disableLogging();
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

            FeedbackServiceProvider::class,
            FeedbackPanelTestServiceProvider::class,

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
        $app['config']->set('lunar.feedback.enabled', true);

        parent::getEnvironmentSetUp($app);
    }

    /**
     * Create the default language and currency used by order factories.
     */
    protected function createLanguageAndCurrency(): void
    {
        if (! Language::where('default', true)->exists()) {
            Language::factory()->create(['code' => 'en', 'default' => true]);
        }

        if (! Currency::where('default', true)->exists()) {
            Currency::factory()->create(['code' => 'EUR', 'default' => true]);
        }
    }

    /**
     * Authenticate as an admin staff member.
     */
    protected function asStaff(): TestCase
    {
        return $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }
}
