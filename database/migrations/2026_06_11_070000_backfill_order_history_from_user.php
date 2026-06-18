<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill: algunas transiciones automáticas (CRONOGRAMA DE PAGO → ABONOS_COMPLETADOS)
     * se guardaron con from_user vacío, por lo que el timeline mostraba el rol en blanco "()".
     * Se completa con el rol (user_type) del usuario que la creó (created_by).
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE order_history h
                SET from_user = u.user_type
                FROM users u
                WHERE (h.from_user = '' OR h.from_user IS NULL)
                  AND h.created_by = u.id
                  AND u.user_type IS NOT NULL
            SQL);
        } else {
            DB::table('order_history')
                ->where(fn ($q) => $q->where('from_user', '')->orWhereNull('from_user'))
                ->get(['id', 'created_by'])
                ->each(function ($h) {
                    $role = DB::table('users')->where('id', $h->created_by)->value('user_type');
                    if ($role) {
                        DB::table('order_history')->where('id', $h->id)->update(['from_user' => $role]);
                    }
                });
        }
    }

    public function down(): void
    {
        // No reversible: no se conserva el valor vacío anterior (era un dato incompleto).
    }
};