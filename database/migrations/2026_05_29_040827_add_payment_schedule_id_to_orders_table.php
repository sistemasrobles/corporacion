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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_schedule_id')->nullable()->after('user_responsible');
        });

        // Migrar datos existentes desde order_details
        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        if ($driver === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('
                UPDATE orders o
                SET payment_schedule_id = od.payment_schedule_id
                FROM order_details od
                WHERE od.order_id = o.id
                AND od.payment_schedule_id IS NOT NULL
            ');
        } else {
            \Illuminate\Support\Facades\DB::statement('
                UPDATE orders o
                INNER JOIN order_details od ON od.order_id = o.id
                SET o.payment_schedule_id = od.payment_schedule_id
                WHERE od.payment_schedule_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_schedule_id');
        });
    }
};
