<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'collection_customer_group', function (Blueprint $table) {
            $table->boolean('display_in_header')->default(true)->after('enabled')->index();
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'collection_customer_group', function (Blueprint $table) {
            $table->dropColumn('display_in_header');
        });
    }
};
