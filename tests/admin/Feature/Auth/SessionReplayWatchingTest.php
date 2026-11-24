<?php

use Illuminate\Support\Facades\Gate;
use Lunar\Admin\Filament\Resources\SessionReplayResource;
use Lunar\Admin\Support\SessionReplayWatching;
use Lunar\Tests\Admin\TestCase;
use Spatie\Permission\Models\Permission;

uses(TestCase::class);

test('session replay playback follows the staff permission and the package viewer stays off', function () {
    Permission::findOrCreate('settings:view-session-replays', 'staff');

    SessionReplayWatching::register();

    expect(config('session-replay.viewer.enabled'))->toBeFalse()
        ->and(config('session-replay.viewer.guard'))->toBe('staff')
        ->and(config('session-replay.viewer.middleware'))->toBe(['web'])
        ->and(SessionReplayWatching::allows())->toBeFalse()
        ->and(Gate::allows('viewSessionReplay'))->toBeFalse();

    $this->actingAs($this->makeStaff(admin: false), 'staff');

    expect(SessionReplayWatching::allows())->toBeFalse()
        ->and(Gate::allows('viewSessionReplay'))->toBeFalse();

    $staff = $this->makeStaff(admin: false);
    $staff->givePermissionTo('settings:view-session-replays');
    $this->actingAs($staff, 'staff');

    expect(SessionReplayWatching::allows())->toBeTrue()
        ->and(Gate::allows('viewSessionReplay'))->toBeTrue();

    $admin = $this->makeStaff(admin: true);
    $this->actingAs($admin, 'staff');

    expect(SessionReplayWatching::allows())->toBeTrue()
        ->and(Gate::allows('viewSessionReplay'))->toBeTrue();

    auth('staff')->logout();

    expect(SessionReplayWatching::allows())->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewSessionReplay'))->toBeFalse();
});

test('the session replay length column formats the stored active time', function () {
    expect(SessionReplayResource::lengthForHumans(0))->toBe('0s')
        ->and(SessionReplayResource::lengthForHumans(10_000))->toBe('10s')
        ->and(SessionReplayResource::lengthForHumans(252_000))->toBe('4m 12s')
        ->and(SessionReplayResource::lengthForHumans(7_200_000))->toBe('2h 00m')
        ->and(SessionReplayResource::lengthForHumans(null))->toBe('0s');
});
