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
        Schema::create($this->prefix.'conversion_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->unsignedBigInteger('cart_id');
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['cart_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'conversion_events');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'conversion_events');
    }
};
