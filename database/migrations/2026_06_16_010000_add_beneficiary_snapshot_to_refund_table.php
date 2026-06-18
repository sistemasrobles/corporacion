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
            // Snapshot del beneficiario al momento de la orden (igual que banco/cuenta).
            $table->string('beneficiary_name', 250)->nullable()->after('beneficiary_bank');
            $table->string('beneficiary_doc', 30)->nullable()->after('beneficiary_name');
        });

        // Backfill: congelar el nombre/documento actuales en las órdenes ya existentes.
        DB::statement("
            UPDATE refund r
               SET beneficiary_name = b.name,
                   beneficiary_doc  = trim(coalesce(b.doc_type,'') || ' ' || coalesce(b.doc_number,''))
              FROM beneficiaries b
             WHERE r.beneficiary_id = b.id
               AND r.beneficiary_name IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('refund', function (Blueprint $table) {
            $table->dropColumn(['beneficiary_name', 'beneficiary_doc']);
        });
    }
};