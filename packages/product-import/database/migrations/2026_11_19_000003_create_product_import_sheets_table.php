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
        Schema::create($this->prefix.'product_import_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')
                ->constrained($this->prefix.'product_imports')
                ->cascadeOnDelete();
            $table->foreignId('product_import_file_id')->nullable()
                ->constrained($this->prefix.'product_import_files')
                ->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->unsignedInteger('header_row')->default(1);
            $table->unsignedInteger('row_count')->default(0);
            $table->boolean('skipped')->default(false);
            $table->json('columns')->nullable();
            $table->string('analysis_status', 32)->nullable();
            $table->json('ai_proposal')->nullable();
            $table->json('analysis_error')->nullable();
            $table->unsignedTinyInteger('analysis_attempts')->default(0);
            $table->json('mapping')->nullable();
            $table->foreignId('offered_profile_id')->nullable()
                ->constrained($this->prefix.'product_import_profiles')
                ->nullOnDelete();
            $table->decimal('offered_profile_score', 5, 4)->nullable();
            $table->foreignId('profile_id')->nullable()
                ->constrained($this->prefix.'product_import_profiles')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['product_import_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_import_sheets');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'product_import_sheets');
    }
};
