<?php

namespace Lunar\Tests\shippingAddon;

use Cartalyst\Converter\Laravel\ConverterServiceProvider;
use Filament\FilamentServiceProvider;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Lunar\Addons\Shipping\ShippingServiceProvider;
use Lunar\Admin\LunarPanelProvider;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\LunarServiceProvider;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRateAmount;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\shippingAddon\Providers\ShippingAddonPanelTestServiceProvider;
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
        $this->loadMigrationsFrom(__DIR__.'/../../packages/locations/database/migrations');

        activity()->disableLogging();

        $this->freezeTime();
    }

    protected function getPackageProviders($app)
    {
        return [
            LunarServiceProvider::class,
            LunarPanelProvider::class,

            FilamentServiceProvider::class,

            ShippingAddonPanelTestServiceProvider::class,
            ShippingServiceProvider::class,

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
     * Create a user for testing purposes.
     */
    protected function createUser(): User
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        return $user;
    }

    /**
     * Create and configure a new Cart instance.
     *
     * @param  Currency  $currency  The currency to be associated with the cart.
     * @return Cart The created and configured Cart instance.
     */
    protected function createCart(Currency $currency): Cart
    {
        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
        ]);

        $taxClass = TaxClass::factory()->create();

        $taxClass->taxRateAmounts()->create(
            TaxRateAmount::factory()->make([
                'percentage' => 20,
                'tax_class_id' => $taxClass->id,
            ])->toArray()
        );

        ShippingManifest::addOption(
            $shippingOption = new ShippingOption(
                name: 'Basic Delivery',
                description: 'Basic Delivery',
                identifier: 'BASDEL',
                price: new Price(500, $cart->currency, 1),
                taxClass: $taxClass
            )
        );

        $shipping = CartAddress::factory()->make([
            'type' => 'shipping',
            'country_id' => Country::factory(),
            'first_name' => 'Santa',
            'line_one' => '123 Elf Road',
            'city' => 'Lapland',
            'postcode' => 'SHIPP',
        ]);

        $cart->setShippingAddress($shipping);
        $cart->setShippingOption($shippingOption);

        return $cart;
    }
}
