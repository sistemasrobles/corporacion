<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            // AA responsable de la orden (puede diferir de created_by cuando la crea un GA).
            $table->unsignedBigInteger('responsible_id')->nullable()->after('created_by');
        });

        // Las órdenes ya existentes: el responsable es quien las creó.
        DB::statement('UPDATE refund SET responsible_id = created_by WHERE responsible_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            $table->dropColumn('responsible_id');
        });
    }
};