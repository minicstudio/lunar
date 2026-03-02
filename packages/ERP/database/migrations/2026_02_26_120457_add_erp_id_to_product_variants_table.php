<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = $this->prefix.'product_variants';

        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->bigInteger('erp_id')->after('product_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = $this->prefix.'product_variants';

        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('erp_id');
            });
        }
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasColumn($this->prefix.'product_variants', 'erp_id');
    }
};
