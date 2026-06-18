<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSequences extends Command
{
    protected $signature = 'db:sync-sequences {--table=* : Limitar a tablas específicas}';

    protected $description = 'Reajusta las secuencias id de PostgreSQL a MAX(id) (corrige el desfase tras migrar de MySQL).';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->warn('La conexión no es PostgreSQL; no hay secuencias que reajustar.');
            return self::SUCCESS;
        }

        // Tablas indicadas, o todas las del esquema public que tengan secuencia en "id".
        $tables = $this->option('table') ?: DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'id')
            ->whereNotNull('column_default')
            ->where('column_default', 'like', 'nextval(%')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $fixed = 0;
        foreach ($tables as $table) {
            $seq = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS seq", [$table])->seq ?? null;
            if (!$seq) {
                $this->line("· {$table}: sin secuencia en id (omitida)");
                continue;
            }

            $max = (int) (DB::selectOne("SELECT COALESCE(MAX(id), 0) AS m FROM \"{$table}\"")->m ?? 0);
            // setval(seq, GREATEST(max,1)) deja is_called=true → el próximo id será max+1 (o 1 si está vacía).
            DB::statement("SELECT setval(?, GREATEST(?, 1))", [$seq, $max]);

            $next = $max > 0 ? $max + 1 : 1;
            $this->info("✓ {$table}: próximo id = {$next}");
            $fixed++;
        }

        $this->newLine();
        $this->info("Listo. Secuencias reajustadas: {$fixed}.");
        return self::SUCCESS;
    }
}