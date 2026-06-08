<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'payment_schedule_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_schedule_id')->nullable()->after('type_id');
                $table->foreign('payment_schedule_id')->references('id')->on('payment_schedules')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_schedule_id')) {
            // Use raw statement to avoid constraint name mismatch in PostgreSQL
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_schedule_id_foreign');
            }

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_schedule_id');
            });
        }
    }
};
