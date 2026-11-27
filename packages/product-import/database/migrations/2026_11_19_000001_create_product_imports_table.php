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
        Schema::create($this->prefix.'product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->nullable()
                ->constrained($this->prefix.'staff')
                ->nullOnDelete();
            $table->string('status', 32)->index();
            $table->json('options')->nullable();
            $table->json('resolved_options')->nullable();
            $table->json('stats')->nullable();
            $table->string('batch_id')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable()->index();
            $table->timestamp('pruned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_imports');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'product_imports');
    }
};
