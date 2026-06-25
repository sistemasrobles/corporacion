<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código de banco POR ABONO (programación general). UC2 lo registra apenas se
     * sube la constancia de cada cuota ([202]), sin esperar a que terminen todos.
     */
    public function up(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->string('codigo_banco', 100)->nullable()->after('source_currency');
            $table->timestamp('codigo_banco_date')->nullable()->after('codigo_banco');
        });
    }

    public function down(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->dropColumn(['codigo_banco', 'codigo_banco_date']);
        });
    }
};