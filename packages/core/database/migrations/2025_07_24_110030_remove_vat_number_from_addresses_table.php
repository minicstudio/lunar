<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'addresses', function (Blueprint $table) {
            $table->dropColumn('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'addresses', function (Blueprint $table) {
            $table->string('vat_number')->after('company_name')->nullable();
        });
    }
};
