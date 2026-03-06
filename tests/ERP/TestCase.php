<?php

namespace Lunar\Tests\ERP;

use Cartalyst\Converter\Laravel\ConverterServiceProvider;
use Filament\FilamentServiceProvider;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Lunar\Admin\LunarPanelProvider;
use Lunar\ERP\ErpServiceProvider;
use Lunar\Locations\LocationsServiceProvider;
use Lunar\LunarServiceProvider;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Tests\ERP\Providers\ErpPanelTestServiceProvider;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\TestCase as BaseTestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/locations/database/migrations');

        activity()->disableLogging();

        $this->freezeTime();
    }

    protected function getPackageProviders($app)
    {
        return [
            LunarServiceProvider::class,
            LunarPanelProvider::class,

            FilamentServiceProvider::class,

            ErpPanelTestServiceProvider::class,
            ErpServiceProvider::class,
            LocationsServiceProvider::class,

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
        $this->replaceModelsForTesting();
    }

    /**
     * Create default and non-default languages if they do not exist.
     *
     * @return void
     */
    protected function createLanguages(): void
    {
        $defaultLanguage = Language::where('code', 'en')->first();

        if (! $defaultLanguage) {
            Language::factory()->create([
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
            Currency::factory()->create([
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

    /**
     * Create a default customer group if it does not exist.
     */
    protected function createCustomerGroup(): void
    {
        $defaultGroup = CustomerGroup::where('default', true)->first();

        if (! $defaultGroup) {
            CustomerGroup::factory()->create([
                'default' => true,
                'name' => 'Retail',
                'handle' => 'retail',
            ]);
        }
    }

    /**
     * Create a default channel if it does not exist.
     */
    protected function createChannel(): void
    {
        $defaultChannel = Channel::where('default', true)->first();

        if (! $defaultChannel) {
            Channel::factory()->create([
                'default' => true,
            ]);
        }
    }

    /**
     * Create a user for testing purposes.
     *
     * @return User
     */
    protected function createUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
