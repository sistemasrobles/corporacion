<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            // Fecha en que el AA necesita tener el fondo disponible.
            $table->date('needed_date')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            $table->dropColumn('needed_date');
        });
    }
};