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
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_schedule_id')) {
                $table->dropForeign(['payment_schedule_id']);
                $table->dropColumn('payment_schedule_id');
            }
        });
    }
};
