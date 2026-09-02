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
        Schema::create($this->prefix.'loyalty_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->unique()
                ->constrained($this->prefix.'customers')
                ->cascadeOnDelete();

            $table->integer('balance')->default(0);
            $table->integer('lifetime_earned')->default(0);
            $table->integer('lifetime_spent')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'loyalty_accounts');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'loyalty_accounts');
    }
};
