<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\ErpServiceProvider;
use Lunar\ERP\Exceptions\ErpInitializationException;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpProvider;
use Lunar\ERP\Providers\Smartbill\SmartbillApiClient;
use Lunar\ERP\Providers\Smartbill\SmartbillErpProvider;

/**
 * Re-run the provider's schedule registration against the current config and
 * return the scheduled `erp:sync-*` events keyed by command name.
 *
 * @return array<string, Event>
 */
function scheduledErpSyncEvents(): array
{
    $provider = app()->getProvider(ErpServiceProvider::class);

    (fn () => $this->registerSchedule())->call($provider);

    return collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => preg_match('/erp:sync-[\w-]+/', (string) $event->command) === 1)
        ->keyBy(fn (Event $event) => preg_replace('/^.*(erp:sync-[\w-]+).*$/', '$1', (string) $event->command))
        ->all();
}

beforeEach(function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister', 'smartbill']);

    Config::set('lunar.erp.magister.enabled', true);
    Config::set('lunar.erp.magister.provider_class', MagisterErpProvider::class);
    Config::set('lunar.erp.magister.client_class', MagisterApiClient::class);

    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.smartbill.provider_class', SmartbillErpProvider::class);
    Config::set('lunar.erp.smartbill.client_class', SmartbillApiClient::class);

    Config::set('lunar.erp.sync', [
        'products' => [],
        'orders' => [],
        'stock' => [],
        'localities' => [],
        'attributes' => [],
    ]);
});

it('does not schedule any sync command when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);
    Config::set('lunar.erp.sync.products', ['magister']);

    expect(scheduledErpSyncEvents())->toBe([]);
});

it('does not schedule sync commands for a billing-only provider setup', function () {
    Config::set('lunar.erp.providers', ['smartbill']);
    Config::set('lunar.erp.actions.billing', ['smartbill']);

    expect(scheduledErpSyncEvents())->toBe([]);
});

it('schedules only sync features that have an enabled and allowed provider', function () {
    Config::set('lunar.erp.sync.products', ['magister']);
    Config::set('lunar.erp.sync.stock', ['magister']);

    expect(array_keys(scheduledErpSyncEvents()))->toBe(['erp:sync-products', 'erp:sync-stock']);
});

it('does not schedule sync features whose providers are not enabled', function () {
    Config::set('lunar.erp.magister.enabled', false);
    Config::set('lunar.erp.sync.products', ['magister']);
    Config::set('lunar.erp.sync.orders', ['magister']);

    expect(scheduledErpSyncEvents())->toBe([]);
});

it('uses the configured cron expression for each scheduled sync command', function () {
    Config::set('lunar.erp.sync.orders', ['magister']);
    Config::set('lunar.erp.schedule.orders', '*/7 * * * *');

    $events = scheduledErpSyncEvents();

    expect($events)->toHaveKey('erp:sync-order-statuses')
        ->and($events['erp:sync-order-statuses']->expression)->toBe('*/7 * * * *');
});

it('throws when a scheduled sync feature has no cron expression', function () {
    Config::set('lunar.erp.sync.attributes', ['magister']);
    Config::set('lunar.erp.schedule.attributes', null);

    scheduledErpSyncEvents();
})->throws(ErpInitializationException::class, 'ERP sync feature [attributes] has providers configured but no schedule expression.');
