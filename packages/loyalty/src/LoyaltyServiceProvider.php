<?php

namespace Lunar\Loyalty;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\LunarPanelManager;
use Lunar\Facades\ModelManifest;
use Lunar\Loyalty\Console\AwardBirthdayPointsCommand;
use Lunar\Loyalty\Console\ExpireLoyaltyPointsCommand;
use Lunar\Loyalty\Console\NotifyExpiringLoyaltyPointsCommand;
use Lunar\Loyalty\Console\RecalculateBalancesCommand;
use Lunar\Loyalty\Database\State\EnsureLoyaltyPermissions;
use Lunar\Loyalty\Listeners\RegistrationListener;
use Lunar\Loyalty\Mixins\CustomerMixin;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Observers\OrderObserver;
use Lunar\Loyalty\Observers\TransactionObserver;
use Lunar\Loyalty\Pipelines\Cart\AdjustCartTotalsForLoyalty;
use Lunar\Loyalty\Pipelines\Cart\ApplyLoyaltyRedemption;
use Lunar\Loyalty\Pipelines\Order\Creation\FinalizeLoyaltySpend;
use Lunar\Loyalty\Services\LoyaltyAccountManager;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Loyalty\Services\LoyaltyExpirationService;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Loyalty\Validation\Cart\LoyaltyRedemptionValidator;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Lunar\Pipelines\Cart\ApplyDiscounts;
use Lunar\Pipelines\Cart\Calculate;
use Lunar\Pipelines\Order\Creation\FillOrderFromCart;

class LoyaltyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/loyalty.php', 'lunar.loyalty');

        $this->app->singleton(LoyaltyLedger::class);
        $this->app->singleton(LoyaltyAccountManager::class);
        $this->app->singleton(LoyaltyEngine::class);
        $this->app->singleton(LoyaltyRedeemer::class);
        $this->app->singleton(LoyaltyExpirationService::class);

        $this->registerAdminExtensions();
        $this->registerPipelines();
        $this->registerValidators();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->registerModelManifest();
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerModelMixins();
        $this->registerRelations();
        $this->registerMorphMap();
        $this->registerObservers();
        $this->registerEventListeners();
        $this->registerStateListeners();
        $this->registerSchedule();
    }

    /**
     * Load package assets like migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.loyalty');

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Publish package config and migrations.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/loyalty.php' => config_path('lunar/loyalty.php'),
        ], 'lunar.loyalty.config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'lunar.loyalty.migrations');
    }

    /**
     * Register earn/spend transaction relations on Lunar's Order.
     *
     * Storefront Order extends this class, so Eloquent resolves the relations there too.
     */
    protected function registerRelations(): void
    {
        Order::resolveRelationUsing('loyaltyEarnTransaction', function ($order) {
            return $this->orderLoyaltyTransactionRelation($order, LoyaltyEventKey::orderEarn(...));
        });

        Order::resolveRelationUsing('loyaltySpendTransaction', function ($order) {
            return $this->orderLoyaltyTransactionRelation($order, LoyaltyEventKey::orderSpend(...));
        });
    }

    /**
     * Build an order loyalty transaction relation, safe for unsaved orders.
     *
     * @param  callable(int|string): string  $eventKeyForOrderId
     */
    protected function orderLoyaltyTransactionRelation(Order $order, callable $eventKeyForOrderId): HasOne
    {
        $relation = $order->hasOne(LoyaltyTransaction::modelClass(), 'reference_id', 'id')
            ->where('reference_type', $order->getMorphClass());

        $orderId = $order->getKey();

        if ($orderId === null) {
            return $relation->whereRaw('1 = 0');
        }

        return $relation->where('event_key', $eventKeyForOrderId($orderId));
    }

    /**
     * Register morph map for polymorphic relations.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            'loyalty_account' => LoyaltyAccount::modelClass(),
            'loyalty_transaction' => LoyaltyTransaction::modelClass(),
        ]);
    }

    /**
     * Register model manifest directory.
     */
    protected function registerModelManifest(): void
    {
        ModelManifest::addDirectory(__DIR__.'/Models');
    }

    /**
     * Register console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireLoyaltyPointsCommand::class,
                NotifyExpiringLoyaltyPointsCommand::class,
                AwardBirthdayPointsCommand::class,
                RecalculateBalancesCommand::class,
            ]);
        }
    }

    /**
     * Register mixins for models.
     */
    protected function registerModelMixins(): void
    {
        Customer::mixin(new CustomerMixin);
    }

    /**
     * Register observers for models.
     */
    protected function registerObservers(): void
    {
        Order::observe(OrderObserver::class);
        Transaction::observe(TransactionObserver::class);
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        Customer::created(function (Customer $customer) {
            app(RegistrationListener::class)->handle($customer);
        });
    }

    /**
     * Register state listeners for migration events.
     */
    protected function registerStateListeners(): void
    {
        foreach ([EnsureLoyaltyPermissions::class] as $state) {
            $class = new $state;

            Event::listen([MigrationsStarted::class], [$class, 'prepare']);
            Event::listen([MigrationsEnded::class, NoPendingMigrations::class], [$class, 'run']);
        }
    }

    /**
     * Register the schedule.
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            if (! config('lunar.loyalty.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $config = config('lunar.loyalty.schedule', []);

            if ($cron = $config['expire'] ?? null) {
                $schedule->command('loyalty:expire-points')->cron($cron);
            }

            if ($cron = $config['notify'] ?? null) {
                $schedule->command('loyalty:notify-expiring-points')->cron($cron);
            }

            if ($cron = $config['birthday'] ?? null) {
                $schedule->command('loyalty:award-birthday-points')->cron($cron)
                    ->when(fn () => config('lunar.loyalty.scheduled_rewards.birthday.enabled', false));
            }

            if ($cron = $config['recalculate_balances'] ?? null) {
                $schedule->command('loyalty:recalculate-balances')->cron($cron);
            }
        });
    }

    /**
     * Inject loyalty pipelines into cart and order creation.
     */
    protected function registerPipelines(): void
    {
        $cartPipelines = config('lunar.cart.pipelines.cart', []);
        $discountIndex = array_search(ApplyDiscounts::class, $cartPipelines, true);

        if ($discountIndex !== false && ! in_array(ApplyLoyaltyRedemption::class, $cartPipelines, true)) {
            array_splice($cartPipelines, $discountIndex + 1, 0, [ApplyLoyaltyRedemption::class]);
            config(['lunar.cart.pipelines.cart' => $cartPipelines]);
        }

        $cartPipelines = config('lunar.cart.pipelines.cart', []);
        $calculateIndex = array_search(Calculate::class, $cartPipelines, true);

        if ($calculateIndex !== false && ! in_array(AdjustCartTotalsForLoyalty::class, $cartPipelines, true)) {
            array_splice($cartPipelines, $calculateIndex + 1, 0, [AdjustCartTotalsForLoyalty::class]);
            config(['lunar.cart.pipelines.cart' => $cartPipelines]);
        }

        $orderPipelines = config('lunar.orders.pipelines.creation', []);
        $fillIndex = array_search(FillOrderFromCart::class, $orderPipelines, true);

        if ($fillIndex !== false && ! in_array(FinalizeLoyaltySpend::class, $orderPipelines, true)) {
            array_splice($orderPipelines, $fillIndex + 1, 0, [FinalizeLoyaltySpend::class]);
            config(['lunar.orders.pipelines.creation' => $orderPipelines]);
        }
    }

    /**
     * Register cart validators.
     */
    protected function registerValidators(): void
    {
        $validators = config('lunar.cart.validators.order_create', []);

        if (! in_array(LoyaltyRedemptionValidator::class, $validators, true)) {
            $validators[] = LoyaltyRedemptionValidator::class;
            config(['lunar.cart.validators.order_create' => $validators]);
        }
    }

    /**
     * Register Lunar admin resource extensions.
     */
    protected function registerAdminExtensions(): void
    {
        $this->app->resolving('lunar-panel', function (LunarPanelManager $panel): void {
            $panel->extensions([
                \Lunar\Admin\Filament\Resources\CustomerResource::class => \Lunar\Loyalty\Filament\Resources\CustomerResource::class,
                \Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder::class => \Lunar\Loyalty\Filament\Extensions\ManageOrderExtension::class,
            ]);
        });
    }
}
