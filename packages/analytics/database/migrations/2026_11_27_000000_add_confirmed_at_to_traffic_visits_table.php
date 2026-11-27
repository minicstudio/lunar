<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Visits recorded before confirmation existed count as confirmed, so the
     * report keeps showing them.
     */
    public function up(): void
    {
        Schema::table($this->prefix.'traffic_visits', function (Blueprint $table) {
            $table->timestamp('confirmed_at')
                ->nullable()
                ->after('created_at')
                ->comment('When a browser confirmed the visit; reports count only confirmed visits.');
        });

        DB::table($this->prefix.'traffic_visits')->update(['confirmed_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table($this->prefix.'traffic_visits', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return Schema::hasTable($this->prefix.'traffic_visits')
            && ! Schema::hasColumn($this->prefix.'traffic_visits', 'confirmed_at');
    }
};
