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
        Schema::create($this->prefix.'product_import_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')
                ->constrained($this->prefix.'product_imports')
                ->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('option_key', 191)->default('');
            $table->string('source_value', 500);
            $table->string('normalized_key', 500);
            $table->json('path')->nullable();
            $table->json('sheets')->nullable();
            $table->json('group_keys')->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('variant_count')->default(0);
            $table->string('match_type', 16)->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->decimal('score', 5, 4)->nullable();
            $table->string('action', 16)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('create_name', 500)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->unique(['product_import_id', 'type', 'option_key', 'normalized_key'], 'product_import_values_unique_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_import_values');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'product_import_values');
    }
};
