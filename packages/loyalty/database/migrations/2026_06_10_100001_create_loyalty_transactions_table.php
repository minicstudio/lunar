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
        Schema::create($this->prefix.'loyalty_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loyalty_account_id')
                ->constrained($this->prefix.'loyalty_accounts')
                ->cascadeOnDelete();

            $table->string('type');
            $table->integer('points');
            $table->integer('remaining_points')->nullable();
            $table->string('event_key')->unique();

            $table->nullableMorphs('reference');

            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['loyalty_account_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'loyalty_transactions');
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix.'loyalty_transactions');
    }
};
