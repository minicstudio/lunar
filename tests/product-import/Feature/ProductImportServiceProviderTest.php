<?php

use Filament\Facades\Filament;
use Lunar\ProductImport\ProductImportPlugin;
use Lunar\ProductImport\ProductImportServiceProvider;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class);

test('the product import config is merged with the feature off by default', function () {
    expect(config('lunar.product-import'))->toBeArray()
        ->toHaveKeys([
            'enabled', 'disk', 'max_file_size_kb', 'max_rows', 'queue', 'ai', 'matching',
            'profiles', 'commit', 'retention_days', 'abandoned_days', 'schedule',
        ])
        ->not->toHaveKey('sources')
        ->not->toHaveKey('csv')
        ->not->toHaveKey('defaults')
        ->and(config('lunar.product-import.enabled'))->toBeFalse()
        ->and(config('lunar.product-import.max_file_size_kb'))->toBe(20480)
        ->and(config('lunar.product-import.max_rows'))->toBe(20000)
        ->and(config('lunar.product-import.queue'))->toBe(['name' => null, 'connection' => null])
        ->and(config('lunar.product-import.ai'))->toHaveKeys(['provider', 'model', 'key', 'url', 'timeout', 'sample_size', 'sample_length', 'confidence'])
        ->and(config('lunar.product-import.ai.confidence.preselect'))->toBe(0.60)
        ->and(config('lunar.product-import.ai.confidence.review'))->toBe(0.85)
        ->and(config('lunar.product-import.matching.similar_threshold'))->toBe(0.70)
        ->and(config('lunar.product-import.profiles.match_threshold'))->toBe(0.80)
        ->and(config('lunar.product-import.commit.groups_per_job'))->toBe(25)
        ->and(config('lunar.product-import.retention_days'))->toBe(30)
        ->and(config('lunar.product-import.abandoned_days'))->toBe(7)
        ->and(config('lunar.product-import.schedule.prune'))->toBe('0 3 * * *');
});

test('the plugin is registered on the lunar panel with its id', function () {
    expect(ProductImportPlugin::make()->getId())->toBe('lunar-product-import')
        ->and(Filament::getPanel('lunar')->hasPlugin('lunar-product-import'))->toBeTrue();
});

test('the service provider is registered', function () {
    expect(app()->getProvider(ProductImportServiceProvider::class))->toBeInstanceOf(ProductImportServiceProvider::class);
});
