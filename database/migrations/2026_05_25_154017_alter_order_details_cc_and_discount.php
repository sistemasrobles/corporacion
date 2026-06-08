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
        if (DB::getDriverName() === 'pgsql') {
            // Primero cambiar el tipo de BIGINT a VARCHAR
            DB::statement('ALTER TABLE order_details DROP CONSTRAINT IF EXISTS order_details_cc_id_foreign');
            DB::statement('ALTER TABLE order_details ALTER COLUMN cc_id TYPE VARCHAR(1000) USING (\'[\' || cc_id::text || \']\')');
        } else {
            DB::statement("UPDATE order_details SET cc_id = CONCAT('[', cc_id, ']') WHERE cc_id IS NOT NULL AND cc_id REGEXP '^[0-9]+$'");
            DB::statement('ALTER TABLE order_details DROP INDEX order_details_cc_id_foreign');
            DB::statement('ALTER TABLE order_details MODIFY cc_id VARCHAR(1000) NULL');
        }

        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedInteger('discount_type_id')->nullable()->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('discount_type_id');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_details ALTER COLUMN cc_id TYPE BIGINT USING CAST(cc_id AS BIGINT)');
            Schema::table('order_details', function (Blueprint $table) {
                $table->foreign('cc_id')->references('id')->on('cost_centers');
            });
        } else {
            DB::statement('ALTER TABLE order_details MODIFY cc_id BIGINT UNSIGNED NULL');
            Schema::table('order_details', function (Blueprint $table) {
                $table->foreign('cc_id')->references('id')->on('cost_centers');
            });
        }
    }
};
