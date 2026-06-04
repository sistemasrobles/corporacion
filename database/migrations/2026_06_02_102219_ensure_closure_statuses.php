<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = [
            8 => 'SUSTENTADO',
            9 => 'CONFORME',
            10 => 'CERRADO',
            55 => 'ABONOS_COMPLETADOS',
            91 => 'CODIGO_DE_REGISTRO',
            92 => 'CODIGO_DE_BANCO',
            200 => 'PENDIENTE_POR_DEPOSITO',
            201 => 'DEPOSITADO',
            202 => 'CONSTANCIA_ADJUNTADA',
        ];

        foreach ($statuses as $id => $description) {
            DB::table('status')->updateOrInsert(
                ['id' => $id],
                [
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // No eliminamos estados en el rollback
    }
};
