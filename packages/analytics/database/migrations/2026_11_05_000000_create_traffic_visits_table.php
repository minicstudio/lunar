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
        Schema::create($this->prefix.'traffic_visits', function (Blueprint $table) {
            $table->id();
            $table->string('source', 255);
            $table->string('medium', 255)->nullable();
            $table->string('campaign', 255)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('landing_path', 255);
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['source', 'medium']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'traffic_visits');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'traffic_visits');
    }
};
