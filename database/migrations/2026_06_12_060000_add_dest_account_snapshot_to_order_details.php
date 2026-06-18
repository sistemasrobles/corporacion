<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot de la cuenta bancaria DESTINO (del proveedor) al momento de la orden.
     * Antes la cuenta se resolvía en vivo por supplier_account_id, así que editarla en
     * el módulo de Proveedores cambiaba órdenes históricas. Con estas columnas la orden
     * conserva el banco/cuenta/CCI/moneda tal como estaban — igual que ya se congela la
     * cuenta de ORIGEN en los abonos.
     */
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('dest_bank')->nullable();
            $table->string('dest_account_number')->nullable();
            $table->string('dest_cci')->nullable();
            $table->string('dest_currency', 10)->nullable();
        });

        // Backfill: órdenes existentes toman la cuenta actual de su supplier_account_id.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE order_details d
                SET dest_bank = a.bank,
                    dest_account_number = a.account_number,
                    dest_cci = a.cci,
                    dest_currency = a.currency
                FROM supplier_accounts a
                WHERE d.supplier_account_id = a.id
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['dest_bank', 'dest_account_number', 'dest_cci', 'dest_currency']);
        });
    }
};