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
        Schema::table($this->prefix.'erp_sync_temp', function (Blueprint $table) {
            $table->smallInteger('discount')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table($this->prefix.'erp_sync_temp', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
