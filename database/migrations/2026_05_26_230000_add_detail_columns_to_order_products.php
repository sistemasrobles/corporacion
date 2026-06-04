<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->string('description', 250)->default('')->after('product_id');
            $table->decimal('quantity', 10, 2)->default(1)->after('description');
            $table->decimal('unit_price', 10, 2)->default(0)->after('quantity');
            $table->decimal('sub_total', 10, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn(['description', 'quantity', 'unit_price', 'sub_total']);
        });
    }
};