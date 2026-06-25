<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina el campo redundante orders.motive_observation.
     * Las observaciones se guardan en orders_history (append-only); este campo
     * solo se escribía (la última) y nunca se leía → quedaba sin uso.
     */
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'motive_observation')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('motive_observation');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'motive_observation')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('motive_observation', 250)->default('0');
            });
        }
    }
};