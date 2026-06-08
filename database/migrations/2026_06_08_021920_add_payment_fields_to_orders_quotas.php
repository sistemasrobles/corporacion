<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->string('constancia')->nullable()->after('observacion');   // voucher del abono
            $table->date('deposit_date')->nullable()->after('constancia');    // fecha del depósito
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders_quotas', function (Blueprint $table) {
            $table->dropColumn(['constancia', 'deposit_date']);
        });
    }
};
