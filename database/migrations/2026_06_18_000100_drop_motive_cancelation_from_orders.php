<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina el campo redundante orders.motive_cancelation.
     * El motivo del rechazo se guarda en orders_history (append-only); este campo
     * solo se escribía y nunca se leía → quedaba sin uso.
     */
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'motive_cancelation')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('motive_cancelation');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'motive_cancelation')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('motive_cancelation', 250)->default('0');
            });
        }
    }
};