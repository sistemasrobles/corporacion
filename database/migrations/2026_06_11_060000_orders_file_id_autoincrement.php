<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convierte orders_file.id en columna autoincremental (sequence) y renumera las
     * filas existentes a ids correlativos (1..N). Antes el id se generaba en la app
     * con crc32 (helper fileId), produciendo ids enormes/aleatorios. No hay FKs que
     * referencien orders_file.id, por lo que renumerar es seguro.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            // Renumera a 1..N por orden de id actual (join por ctid para no chocar con la PK).
            DB::statement(<<<'SQL'
                WITH ordered AS (
                    SELECT ctid, ROW_NUMBER() OVER (ORDER BY id) AS rn FROM orders_file
                )
                UPDATE orders_file f
                SET id = o.rn
                FROM ordered o
                WHERE f.ctid = o.ctid
            SQL);

            // Crea la sequence y la conecta como default de la columna.
            DB::statement("CREATE SEQUENCE IF NOT EXISTS orders_file_id_seq");
            DB::statement("ALTER TABLE orders_file ALTER COLUMN id SET DEFAULT nextval('orders_file_id_seq')");
            DB::statement("ALTER SEQUENCE orders_file_id_seq OWNED BY orders_file.id");
            DB::statement("SELECT setval('orders_file_id_seq', (SELECT COALESCE(MAX(id), 0) FROM orders_file))");
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // No se puede restaurar los ids aleatorios anteriores; se quita la autoincrementación.
        DB::statement("ALTER TABLE orders_file ALTER COLUMN id DROP DEFAULT");
        DB::statement("DROP SEQUENCE IF EXISTS orders_file_id_seq");
    }
};