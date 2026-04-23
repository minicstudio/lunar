<?php

namespace Lunar\Database\State;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsurePublishedAtIsSet
{
    public function prepare()
    {
        //
    }

    public function run()
    {
        if (! $this->canRun() || ! $this->shouldRun()) {
            return;
        }

        DB::table($this->table())
            ->whereNull('published_at')
            ->where('status', 'published')
            ->update([
                'published_at' => DB::raw('created_at'),
            ]);
    }

    protected function canRun()
    {
        return Schema::hasTable($this->table()) &&
            Schema::hasColumn($this->table(), 'published_at');
    }

    protected function shouldRun()
    {
        return DB::table($this->table())
            ->whereNull('published_at')
            ->where('status', 'published')
            ->exists();
    }

    protected function table(): string
    {
        $prefix = config('lunar.database.table_prefix');

        return $prefix.'products';
    }
}
