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
        Schema::create($this->prefix."shipping_provider_credentials", function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // e.g. 'sameday', 'dpd'
            $table->string('token');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('provider'); // one active token per provider
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix."shipping_provider_credentials");
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix."shipping_provider_credentials");
    }
};
