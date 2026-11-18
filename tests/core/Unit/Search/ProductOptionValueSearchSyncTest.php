<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class)->group('search');

uses(RefreshDatabase::class);

function recordingScoutLog(): stdClass
{
    $log = new stdClass;
    $log->entries = [];

    $engine = Mockery::mock(NullEngine::class)->makePartial();
    $engine->shouldReceive('update')->zeroOrMoreTimes()->andReturnUsing(function ($models) use ($log): void {
        foreach ($models as $model) {
            $log->entries[] = [
                'action' => 'update',
                'id' => $model->getKey(),
                'data' => $model->toSearchableArray(),
            ];
        }
    });
    $engine->shouldReceive('delete')->zeroOrMoreTimes()->andReturnUsing(function ($models) use ($log): void {
        foreach ($models as $model) {
            $log->entries[] = [
                'action' => 'delete',
                'id' => $model->getKey(),
            ];
        }
    });

    config([
        'scout.driver' => 'recording',
        'scout.queue' => false,
    ]);

    $manager = new EngineManager(app());
    $manager->extend('recording', fn () => $engine);
    app()->instance(EngineManager::class, $manager);

    return $log;
}

test('reindexes the parent option when a value is renamed', function () {
    $option = ProductOption::factory()->create();
    $value = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Small'],
    ]);

    $log = recordingScoutLog();

    $value->update([
        'name' => ['en' => 'Medium'],
    ]);

    $updates = array_values(array_filter($log->entries, fn (array $entry): bool => $entry['action'] === 'update'));

    expect($updates)->not->toBeEmpty()
        ->and(end($updates)['id'])->toBe($option->id)
        ->and(end($updates)['data']['option_'.$value->id.'_en'])->toBe('Medium');
});

test('removes a deleted value from the parent option search document', function () {
    $option = ProductOption::factory()->create();
    $kept = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Small'],
    ]);
    $removed = ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Large'],
    ]);

    $log = recordingScoutLog();

    $removed->delete();

    $updates = array_values(array_filter($log->entries, fn (array $entry): bool => $entry['action'] === 'update'));
    $document = end($updates)['data'];

    expect($updates)->not->toBeEmpty()
        ->and(end($updates)['id'])->toBe($option->id)
        ->and($document)->toHaveKey('option_'.$kept->id.'_en')
        ->and($document['option_'.$kept->id.'_en'])->toBe('Small')
        ->and($document)->not->toHaveKey('option_'.$removed->id.'_en');
});

test('removes the option from the search index when the option is deleted', function () {
    $option = ProductOption::factory()->create();
    ProductOptionValue::factory()->create([
        'product_option_id' => $option->id,
        'name' => ['en' => 'Small'],
    ]);

    $log = recordingScoutLog();

    $option->delete();

    $entries = $log->entries;

    expect($entries)->toBe([
        [
            'action' => 'delete',
            'id' => $option->id,
        ],
    ]);
});
