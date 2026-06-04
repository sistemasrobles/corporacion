<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderPolitica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdatePoliticas extends Command
{
    protected $signature = 'update:politicas';
    protected $description = 'Update politicas based on new flujos';

    public function handle()
    {
        // Crear tabla si no existe
        try {
            DB::statement("
                CREATE TABLE IF NOT EXISTS orders_politicas (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_type VARCHAR(50) NOT NULL,
                    status_id BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_status (user_type, status_id),
                    INDEX idx_user_type (user_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->info('Tabla orders_politicas verificada/creada');
        } catch (\Exception $e) {
            $this->error('Error al crear tabla: ' . $e->getMessage());
        }

        // Limpiar datos existentes
        try {
            DB::table('orders_politicas')->delete();
            $this->info('Datos anteriores eliminados');
        } catch (\Exception $e) {
            $this->warn('No se pudieron eliminar datos anteriores');
        }

        $politicas = [
            // URGENTE - "LE APARECE" a cada rol
            ['user_type' => 'JA', 'status_id' => 1],
            ['user_type' => 'AA', 'status_id' => 1],
            ['user_type' => 'AA', 'status_id' => 2],
            ['user_type' => 'AA', 'status_id' => 5],
            ['user_type' => 'AA', 'status_id' => 55],
            ['user_type' => 'GA', 'status_id' => 2],
            ['user_type' => 'GA', 'status_id' => 5],
            ['user_type' => 'GF', 'status_id' => 3],
            ['user_type' => 'AF', 'status_id' => 8],
            ['user_type' => 'UC1', 'status_id' => 9],
            ['user_type' => 'UC2', 'status_id' => 91],
            ['user_type' => 'UC3', 'status_id' => 92],

            // GENERAL - "LE APARECE" a cada rol
            ['user_type' => 'AA', 'status_id' => 1],
            ['user_type' => 'AA', 'status_id' => 2],
            ['user_type' => 'AA', 'status_id' => 5],
            ['user_type' => 'GA', 'status_id' => 2],
            ['user_type' => 'UC1', 'status_id' => 100],
            ['user_type' => 'GF', 'status_id' => 102],
            ['user_type' => 'UC3', 'status_id' => 91],
            ['user_type' => 'UC5', 'status_id' => 101],
            ['user_type' => 'UC2', 'status_id' => 55],
            ['user_type' => 'UC4', 'status_id' => 92],
        ];

        foreach ($politicas as $politica) {
            try {
                DB::table('orders_politicas')->insert($politica);
            } catch (\Exception $e) {
                $this->error("Error al insertar {$politica['user_type']} {$politica['status_id']}: " . $e->getMessage());
            }
        }

        $this->info('✅ Políticas actualizadas correctamente');
        $count = DB::table('orders_politicas')->count();
        $this->info("Total de políticas: $count");
    }
}