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
        Schema::create($this->prefix.'reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained($this->prefix.'orders')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedBigInteger('reviewable_id')->nullable();
            $table->string('reviewable_type')->nullable();

            $table->json('attribute_data');

            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'reviews');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'reviews');
    }
};
