<?php

namespace Lunar\Database\State;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConvertCancelledStatusToCanceled
{
    public function prepare()
    {
        //
    }

    public function run(): void
    {
        if (! $this->canRun() || ! $this->shouldRun()) {
            return;
        }

        DB::table($this->table())
            ->where('status', 'cancelled')
            ->update(['status' => 'canceled']);
    }

    protected function canRun(): bool
    {
        return Schema::hasTable($this->table())
            && Schema::hasColumn($this->table(), 'status');
    }

    protected function shouldRun(): bool
    {
        return DB::table($this->table())
            ->where('status', 'cancelled')
            ->exists();
    }

    protected function table(): string
    {
        $prefix = config('lunar.database.table_prefix');

        return $prefix.'orders';
    }
}
