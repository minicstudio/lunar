<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'addresses', function (Blueprint $table) {
            $table->foreignId('address_customer_type_id')->nullable()->after('last_name')->constrained($this->prefix.'address_customer_types');
        });
        Schema::table($this->prefix.'order_addresses', function (Blueprint $table) {
            $table->foreignId('address_customer_type_id')->nullable()->after('last_name')->constrained($this->prefix.'address_customer_types');
        });
        Schema::table($this->prefix.'cart_addresses', function (Blueprint $table) {
            $table->foreignId('address_customer_type_id')->nullable()->after('last_name')->constrained($this->prefix.'address_customer_types');
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'addresses', function (Blueprint $table) {
            $table->dropForeign(['address_customer_type_id']);
            $table->dropColumn('address_customer_type_id');
        });

        Schema::table($this->prefix.'order_addresses', function (Blueprint $table) {
            $table->dropForeign(['address_customer_type_id']);
            $table->dropColumn('address_customer_type_id');
        });

        Schema::table($this->prefix.'cart_addresses', function (Blueprint $table) {
            $table->dropForeign(['address_customer_type_id']);
            $table->dropColumn('address_customer_type_id');
        });
    }
};
