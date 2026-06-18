<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crea la gestión "GESTION GLOBAL" y la asocia (type_user) a todos los usuarios GA.
     * Así el GA queda mapeado a una gestión válida: store() resuelve su type_id por el FK
     * a `types` y el GA puede crear órdenes (siendo él mismo el responsable).
     */
    public function up(): void
    {
        // Secuencias desfasadas tras migrar de MySQL → evita choques de PK al insertar.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('types','id'), GREATEST((SELECT COALESCE(MAX(id),0) FROM types), 1))");
            DB::statement("SELECT setval(pg_get_serial_sequence('type_user','id'), GREATEST((SELECT COALESCE(MAX(id),0) FROM type_user), 1))");
        }

        $typeId = DB::table('types')->whereRaw('UPPER(descripcion) = ?', ['GESTION GLOBAL'])->value('id');
        if (!$typeId) {
            $typeId = DB::table('types')->insertGetId([
                'descripcion' => 'GESTION GLOBAL',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        foreach (DB::table('users')->where('user_type', 'GA')->pluck('id') as $uid) {
            $exists = DB::table('type_user')->where('type_id', $typeId)->where('user_id', $uid)->exists();
            if (!$exists) {
                DB::table('type_user')->insert([
                    'type_id'    => $typeId,
                    'user_id'    => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $typeId = DB::table('types')->whereRaw('UPPER(descripcion) = ?', ['GESTION GLOBAL'])->value('id');
        if ($typeId) {
            DB::table('type_user')->where('type_id', $typeId)->delete();
            DB::table('types')->where('id', $typeId)->delete();
        }
    }
};