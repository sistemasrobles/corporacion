<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            // N° de operación del depósito (se captura al subir la constancia)
            $table->string('operation_number', 100)->nullable()->after('constancia');
            // Cuenta de origen seleccionada al registrar el depósito (snapshot desde companies)
            $table->unsignedBigInteger('source_company_id')->nullable()->after('operation_number');
            $table->string('source_bank', 150)->nullable()->after('source_company_id');
            $table->string('source_account_number', 60)->nullable()->after('source_bank');
            $table->string('source_cci', 60)->nullable()->after('source_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->dropColumn([
                'operation_number', 'source_company_id',
                'source_bank', 'source_account_number', 'source_cci',
            ]);
        });
    }
};