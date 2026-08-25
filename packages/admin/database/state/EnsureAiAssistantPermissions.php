<?php

namespace Lunar\Admin\Database\State;

use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;

class EnsureAiAssistantPermissions
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

        $permissions = [
            'ai:manage-settings',
            'ai:chat',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }
    }
}
