<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->prefix.'customers', function (Blueprint $table) {
            $table->renameColumn('vat_number', 'tax_identifier');
        });
    }

    public function down(): void
    {
        Schema::table($this->prefix.'customers', function (Blueprint $table) {
            $table->renameColumn('tax_identifier', 'vat_number');
        });
    }
};
