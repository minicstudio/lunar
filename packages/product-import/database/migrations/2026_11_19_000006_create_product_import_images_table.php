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
        Schema::create($this->prefix.'product_import_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')
                ->constrained($this->prefix.'product_imports')
                ->cascadeOnDelete();
            $table->foreignId('product_import_row_id')
                ->nullable()
                ->constrained($this->prefix.'product_import_rows')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('url', 2048);
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 16);
            $table->unsignedBigInteger('media_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['product_import_id', 'status'], 'product_import_images_import_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_import_images');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'product_import_images');
    }
};
