<?php

namespace Lunar\ProductImport\Database\State;

use Illuminate\Support\Facades\Schema;
use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;

class EnsureProductImportPermissions
{
    /**
     * The staff permission that gives access to the product import.
     */
    public const PERMISSION = 'catalog:import-products';

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

        Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => $guard,
        ]);
    }
}
