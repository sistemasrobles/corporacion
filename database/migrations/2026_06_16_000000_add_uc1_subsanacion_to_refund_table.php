<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            // Marca que la orden volvió al AA por una observación del UC1: al re-rendir regresa directo al UC1 (no al AF).
            $table->boolean('uc1_subsanacion')->default(false)->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            $table->dropColumn('uc1_subsanacion');
        });
    }
};