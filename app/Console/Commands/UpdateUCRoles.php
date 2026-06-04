<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateUCRoles extends Command
{
    protected $signature = 'update:uc-roles';
    protected $description = 'Update UC user types from UC to UC1-UC5';

    public function handle()
    {
        $mappings = [
            'UC1' => 'CIELO',
            'UC2' => 'PIERO',
            'UC3' => 'BRILLIT',
            'UC4' => 'MELANY',
            'UC5' => 'ANNICK',
        ];

        // Primero agregar los tipos UC a la tabla user_types
        $this->info('Agregando tipos UC a user_types...');
        foreach (array_keys($mappings) as $ucType) {
            $exists = DB::table('user_types')->where('prefijo', $ucType)->exists();
            if (!$exists) {
                DB::table('user_types')->insert(['prefijo' => $ucType, 'description' => $ucType]);
                $this->info("  ✅ {$ucType} agregado");
            }
        }

        // Luego actualizar los usuarios
        $this->info("\nActualizando usuarios...");
        foreach ($mappings as $ucType => $userName) {
            $updated = DB::table('users')
                ->where('user_type', 'UC')
                ->whereRaw("UPPER(name) LIKE ?", ["%$userName%"])
                ->update(['user_type' => $ucType]);

            if ($updated > 0) {
                $this->info("✅ {$userName} -> {$ucType}");
            } else {
                $this->warn("⚠️  No encontrado: {$userName}");
            }
        }

        $this->info("\n✅ Actualización completada");
    }
}
