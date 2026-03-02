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
        Schema::create($this->prefix.'erp_sync_temp', function (Blueprint $table) {
            $table->id();
            $table->string('erp_id')->unique();
            $table->string('name');
            $table->string('sku');
            $table->unsignedBigInteger('price');
            $table->integer('stock')->default(0);
            $table->string('category_1')->nullable();
            $table->string('category_2')->nullable();
            $table->json('provider_data')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['erp_id']);
            $table->index(['name']);
            $table->index(['sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'erp_sync_temp');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'erp_sync_temp');
    }
};
