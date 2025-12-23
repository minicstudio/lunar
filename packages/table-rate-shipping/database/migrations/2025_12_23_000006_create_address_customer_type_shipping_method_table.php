<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create($this->prefix.'address_customer_type_shipping_method', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('address_customer_type_id')
                ->constrained($this->prefix.'address_customer_types')
                ->name('act_sm_address_customer_type_id_foreign')
                ->onDelete('cascade');
            $table->foreignId('shipping_method_id')
                ->constrained($this->prefix.'shipping_methods')
                ->name('act_sm_shipping_method_id_foreign')
                ->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'address_customer_type_shipping_method');
    }
};
