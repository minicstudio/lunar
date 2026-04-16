<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'products', function (Blueprint $table) {
            $table->timestamp('published_at')->after('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'products', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
