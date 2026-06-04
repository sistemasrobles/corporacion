<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->json('items')->nullable()->after('observation');
            $table->unsignedBigInteger('supplier_account_id')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['items', 'supplier_account_id']);
        });
    }
};