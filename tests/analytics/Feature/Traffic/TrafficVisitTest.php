<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

test('the table is lunar_traffic_visits with exactly eight columns and no personal data', function () {
    expect((new TrafficVisit)->getTable())->toBe('lunar_traffic_visits')
        ->and(Schema::getColumnListing('lunar_traffic_visits'))->toEqualCanonicalizing([
            'id',
            'source',
            'medium',
            'campaign',
            'referrer_host',
            'landing_path',
            'created_at',
            'confirmed_at',
        ]);
});

test('model:prune deletes unconfirmed visits older than 30 days and keeps the rest', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 12:00:00'));

    $old = TrafficVisit::factory()->unconfirmed()->create(['created_at' => now()->subDays(31)]);
    $recent = TrafficVisit::factory()->unconfirmed()->create(['created_at' => now()->subDays(29)]);
    $confirmed = TrafficVisit::factory()->create(['created_at' => now()->subDays(90)]);

    $this->artisan('model:prune', ['--model' => [TrafficVisit::class]])->assertSuccessful();

    expect(TrafficVisit::query()->pluck('id')->sort()->values()->all())->toBe([$recent->id, $confirmed->id]);
    expect(TrafficVisit::query()->find($old->id))->toBeNull();
});

test('the confirmed_at migration marks the visits already stored as confirmed at their creation', function () {
    Schema::table('lunar_traffic_visits', fn (Blueprint $table) => $table->dropColumn('confirmed_at'));
    DB::table('lunar_traffic_visits')->insert([
        'source' => 'direct',
        'landing_path' => '/',
        'created_at' => '2026-11-20 10:00:00',
    ]);

    $migration = require __DIR__.'/../../../../packages/analytics/database/migrations/2026_11_27_000000_add_confirmed_at_to_traffic_visits_table.php';

    expect($migration->shouldRun())->toBeTrue();

    $migration->up();

    expect(DB::table('lunar_traffic_visits')->value('confirmed_at'))->toBe('2026-11-20 10:00:00')
        ->and($migration->shouldRun())->toBeFalse();
});
