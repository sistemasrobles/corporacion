<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convierte cc_id enteros existentes a JSON array antes de cambiar el tipo
        DB::statement("UPDATE order_details SET cc_id = CONCAT('[', cc_id, ']') WHERE cc_id IS NOT NULL AND cc_id REGEXP '^[0-9]+$'");

        // Solo tiene índice (sin FK constraint), se elimina directamente
        DB::statement('ALTER TABLE order_details DROP INDEX order_details_cc_id_foreign');

        // cc_id pasa a VARCHAR(1000) para almacenar JSON de múltiples centros de costo
        DB::statement('ALTER TABLE order_details MODIFY cc_id VARCHAR(1000) NULL');

        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedInteger('discount_type_id')->nullable()->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('discount_type_id');
        });
        DB::statement('ALTER TABLE order_details MODIFY cc_id BIGINT UNSIGNED NULL');
        Schema::table('order_details', function (Blueprint $table) {
            $table->foreign('cc_id')->references('id')->on('cost_centers');
        });
    }
};
