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
        Schema::table($this->prefix.'localities', function (Blueprint $table) {
            $table->dropUnique(
                $this->prefix.'localities_postal_code_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table($this->prefix.'localities', function (Blueprint $table) {
            $table->unique('postal_code');
        });
    }
};
