<?php

namespace Lunar\Review\Database\State;

use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;

class EnsureReviewPermissions
{
    public function prepare()
    {
        //
    }

    public function run()
    {
        $guard = LunarPanel::getPanel()->getAuthGuard();

        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['permissions'])) {
            return;
        }

        $permission = 'sales:reviews:manage';
        
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => $guard,
        ]);
    }
}
