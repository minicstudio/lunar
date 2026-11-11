<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

test('creating a visit stores every column', function () {
    $visit = TrafficVisit::create([
        'source' => 'arukereso.hu',
        'medium' => 'referral',
        'campaign' => 'black-friday',
        'referrer_host' => 'arukereso.hu',
        'landing_path' => '/sale',
    ]);

    $stored = TrafficVisit::query()->findOrFail($visit->id);

    expect($stored->source)->toBe('arukereso.hu')
        ->and($stored->medium)->toBe('referral')
        ->and($stored->campaign)->toBe('black-friday')
        ->and($stored->referrer_host)->toBe('arukereso.hu')
        ->and($stored->landing_path)->toBe('/sale')
        ->and($stored->created_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('a visit has no updated_at', function () {
    $visit = TrafficVisit::factory()->create();

    expect($visit->getUpdatedAtColumn())->toBeNull()
        ->and($visit->getAttributes())->not->toHaveKey('updated_at');
});

test('the factory default is a direct visit on the home page', function () {
    $visit = TrafficVisit::factory()->create();

    expect($visit->source)->toBe('direct')
        ->and($visit->medium)->toBe('none')
        ->and($visit->landing_path)->toBe('/');
});

test('the table is lunar_traffic_visits with exactly seven columns and no personal data', function () {
    expect((new TrafficVisit)->getTable())->toBe('lunar_traffic_visits')
        ->and(Schema::getColumnListing('lunar_traffic_visits'))->toEqualCanonicalizing([
            'id',
            'source',
            'medium',
            'campaign',
            'referrer_host',
            'landing_path',
            'created_at',
        ]);
});
