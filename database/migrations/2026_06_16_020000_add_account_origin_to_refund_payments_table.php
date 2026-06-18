<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_payments', function (Blueprint $table) {
            // N° de cuenta de origen (empresa) — para saber a dónde devolver el dinero.
            $table->string('account_origin', 50)->nullable()->after('bank_origin');
        });
    }

    public function down(): void
    {
        Schema::table('refund_payments', function (Blueprint $table) {
            $table->dropColumn('account_origin');
        });
    }
};