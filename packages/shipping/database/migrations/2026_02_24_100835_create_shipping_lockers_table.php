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
        Schema::create($this->prefix."shipping_lockers", function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // e.g. 'sameday', 'fan_courier'
            $table->unsignedInteger('provider_locker_id');
            $table->string('name');
            $table->unsignedTinyInteger('locker_type')->nullable(); // e.g. box (locker) - 0, pickup point (store) - 1
            $table->string('county')->nullable();
            $table->foreignId('county_id')
                ->constrained($this->prefix."shipping_counties")
                ->cascadeOnDelete();
            $table->unsignedInteger('provider_county_id')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('city_id')
                ->constrained($this->prefix."shipping_cities")
                ->cascadeOnDelete();
            $table->unsignedInteger('provider_city_id')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'provider_locker_id']); // Ensure uniqueness per provider
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->prefix."shipping_lockers");
    }

    /**
     * Determine if this migration should run.
     */
    public function shouldRun(): bool
    {
        return ! Schema::hasTable($this->prefix."shipping_lockers");
    }
};
