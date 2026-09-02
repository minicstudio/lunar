<?php

namespace Lunar\Loyalty\Database\State;

use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;

class EnsureLoyaltyPermissions
{
    public function prepare(): void
    {
        //
    }

    public function run(): void
    {
        if (! app()->bound('lunar-panel')) {
            return;
        }

        $guard = LunarPanel::getPanel()->getAuthGuard();

        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['permissions'])) {
            return;
        }

        Permission::firstOrCreate([
            'name' => 'sales:loyalty:manage',
            'guard_name' => $guard,
        ]);
    }
}
