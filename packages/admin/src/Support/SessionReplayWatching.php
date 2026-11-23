<?php

namespace Lunar\Admin\Support;

use Illuminate\Support\Facades\Gate;
use Lunar\Admin\Models\Staff;

class SessionReplayWatching
{
    /**
     * Turn the package viewer off and allow playback for staff who may open the admin screen.
     *
     * Data routes stay on the web middleware. The auth middleware looks for a login
     * route, and a shop without one would answer 500 instead of 403.
     */
    public static function register(): void
    {
        config([
            'session-replay.viewer.enabled' => false,
            'session-replay.viewer.guard' => 'staff',
            'session-replay.viewer.middleware' => ['web'],
        ]);

        Gate::define('viewSessionReplay', function (mixed $user = null, ?object $session = null): bool {
            return static::allows();
        });
    }

    /**
     * The signed-in staff member may watch a recording.
     *
     * Admins pass through the panel permission check without that permission being assigned.
     */
    public static function allows(): bool
    {
        $staff = auth('staff')->user();

        if (! $staff instanceof Staff) {
            return false;
        }

        return $staff->can('settings:view-session-replays');
    }
}
