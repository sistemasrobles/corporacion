<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('codigo_registro', 100)->nullable()->after('items');
            $table->string('codigo_banco', 100)->nullable()->after('codigo_registro');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['codigo_registro', 'codigo_banco']);
        });
    }
};