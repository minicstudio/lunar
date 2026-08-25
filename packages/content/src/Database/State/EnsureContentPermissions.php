<?php

namespace Lunar\Content\Database\State;

use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;

class EnsureContentPermissions
{
    public const MANAGE = 'content:manage';

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

        Permission::firstOrCreate([
            'name' => self::MANAGE,
            'guard_name' => $guard,
        ]);
    }
}
