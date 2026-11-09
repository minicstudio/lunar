<?php

use Filament\Facades\Filament;
use Lunar\Analytics\AnalyticsPlugin;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);

test('the analytics config is merged with traffic recording enabled by default', function () {
    expect(config('lunar.analytics'))->toBeArray()
        ->toHaveKey('traffic')
        ->and(config('lunar.analytics.traffic.enabled'))->toBeTrue()
        ->and(config('lunar.analytics.traffic.referrer_hosts'))->toHaveKey('google.*')
        ->and(config('lunar.analytics.traffic.click_ids'))->toHaveKeys(['gclid', 'gbraid', 'wbraid', 'fbclid']);
});

test('the plugin is registered on the lunar panel with its id', function () {
    expect(AnalyticsPlugin::make()->getId())->toBe('lunar-analytics')
        ->and(Filament::getPanel('lunar')->hasPlugin('lunar-analytics'))->toBeTrue();
});

test('the analytics navigation group label is translated', function () {
    expect(__('lunarpanel.analytics::plugin.navigation.group'))->toBe('Analytics');
});
