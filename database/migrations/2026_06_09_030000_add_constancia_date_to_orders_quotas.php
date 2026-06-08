<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            // Momento en que se subió la constancia del abono (AF/GF)
            $table->dateTime('constancia_date')->nullable()->after('constancia');
        });
    }

    public function down(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->dropColumn('constancia_date');
        });
    }
};