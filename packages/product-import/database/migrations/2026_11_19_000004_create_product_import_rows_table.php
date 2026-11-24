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
        Schema::create($this->prefix.'product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')
                ->constrained($this->prefix.'product_imports')
                ->cascadeOnDelete();
            $table->foreignId('product_import_sheet_id')
                ->constrained($this->prefix.'product_import_sheets')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->json('typed')->nullable();
            $table->json('normalized')->nullable();
            $table->json('overrides')->nullable();
            $table->string('role', 24)->nullable();
            $table->string('source_id')->nullable();
            $table->string('parent_ref')->nullable();
            $table->string('group_key')->nullable();
            $table->string('match_value')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('skip_reason', 16)->nullable();
            $table->json('messages')->nullable();
            $table->string('action', 16)->nullable();
            $table->unsignedBigInteger('matched_product_id')->nullable();
            $table->unsignedBigInteger('matched_variant_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();

            $table->index(['product_import_sheet_id', 'row_number']);
            $table->index(['product_import_id', 'status']);
            $table->index(['product_import_id', 'group_key']);
            $table->index(['product_import_id', 'match_value']);
            $table->index(['product_import_id', 'source_id']);
            $table->index(['product_import_id', 'parent_ref']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_import_rows');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'product_import_rows');
    }
};
