<?php

namespace Lunar\Analytics\Database\State;

use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;

class EnsureAnalyticsPermissions
{
    public function prepare(): void
    {
        //
    }

    public function run(): void
    {
        $guard = LunarPanel::getPanel()->getAuthGuard();

        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['permissions'])) {
            return;
        }

        $permission = 'analytics:traffic-sources';

        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => $guard,
        ]);
    }
}
