<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Serie del documento de pago (ej. F001), separada del N° de documento. */
    public function up(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            $table->string('serie', 50)->nullable()->after('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            $table->dropColumn('serie');
        });
    }
};