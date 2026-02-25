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
        Schema::create($this->prefix."shipping_counties", function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // e.g. 'sameday', 'fan_courier'
            $table->unsignedInteger('provider_county_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'provider_county_id']); // Ensure uniqueness per provider
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix."shipping_counties");
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix."shipping_counties");
    }
};
