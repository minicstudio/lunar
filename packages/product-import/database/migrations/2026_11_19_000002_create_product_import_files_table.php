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
        Schema::create($this->prefix.'product_import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')
                ->constrained($this->prefix.'product_imports')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('original_name');
            $table->string('disk');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('encoding', 32)->nullable();
            $table->timestamps();

            $table->index(['product_import_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_import_files');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'product_import_files');
    }
};
