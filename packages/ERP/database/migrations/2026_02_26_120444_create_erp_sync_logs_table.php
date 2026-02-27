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
        Schema::create($this->prefix.'erp_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // 'magister', 'sysgest', etc.
            $table->string('sync_type'); // 'products', 'orders', 'stock'
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('items_processed')->default(0);
            $table->integer('items_total')->default(0);
            $table->text('error_message')->nullable();
            $table->json('sync_data')->nullable(); // Additional sync metadata
            $table->timestamps();

            $table->index(['provider', 'sync_type']);
            $table->index(['status']);
            $table->index(['started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'erp_sync_logs');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'erp_sync_logs');
    }
};
