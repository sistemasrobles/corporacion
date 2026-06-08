<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            // Código de Registro que asigna UC1 por cada documento de pago (comprobante)
            $table->string('registration_code', 100)->nullable()->after('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            $table->dropColumn('registration_code');
        });
    }
};