<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            // Solo aplica a comprobantes "Recibo x Honorario": ¿tiene retención?
            $table->boolean('has_retention')->nullable()->after('registration_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            $table->dropColumn('has_retention');
        });
    }
};