<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->string('source_currency', 3)->nullable()->after('source_cci');
        });

        // Backfill: tomar la moneda de la cuenta de empresa que coincida por N° de cuenta.
        DB::statement("
            UPDATE orders_quotas q
            SET source_currency = ca.currency
            FROM company_accounts ca
            WHERE q.source_account_number = ca.account_number
              AND q.source_currency IS NULL
              AND q.source_account_number IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->dropColumn('source_currency');
        });
    }
};