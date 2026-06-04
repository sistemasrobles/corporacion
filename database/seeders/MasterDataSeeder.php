<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── Formatos (tipos de orden) ────────────────────────────────────────
        DB::table('formats')->insert([
            ['description' => 'Orden de Compra',   'abrev' => 'OC', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Orden de Servicio',  'abrev' => 'OS', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Orden de Caja Chica','abrev' => 'CC', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── Categorías por formato ───────────────────────────────────────────
        $ocId = DB::table('formats')->where('abrev', 'OC')->value('id');
        $osId = DB::table('formats')->where('abrev', 'OS')->value('id');
        $ccId = DB::table('formats')->where('abrev', 'CC')->value('id');

        DB::table('categories')->insert([
            // Orden de Compra
            ['description' => 'Materiales de Oficina',    'format_id' => $ocId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Equipos y Tecnología',     'format_id' => $ocId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Insumos de Limpieza',      'format_id' => $ocId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Muebles y Enseres',        'format_id' => $ocId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Repuestos y Mantenimiento','format_id' => $ocId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            // Orden de Servicio
            ['description' => 'Consultoría',              'format_id' => $osId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Mantenimiento',            'format_id' => $osId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Capacitación',             'format_id' => $osId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Transporte y Logística',   'format_id' => $osId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            // Caja Chica
            ['description' => 'Gastos Menores',           'format_id' => $ccId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['description' => 'Viáticos',                 'format_id' => $ccId, 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── Empresas ─────────────────────────────────────────────────────────
        DB::table('companies')->insert([
            ['name' => 'Corporación Principal S.A.C.', 'ruc' => '20123456789', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Filial Norte E.I.R.L.',        'ruc' => '20987654321', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ── Tipos de gestión (si están vacíos) ────────────────────────────────
        if (DB::table('types')->count() === 0) {
            DB::table('types')->insert([
                ['descripcion' => 'Logística',    'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['descripcion' => 'Oficinas',     'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['descripcion' => 'TI',           'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['descripcion' => 'Operaciones',  'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['descripcion' => 'Recursos Humanos', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // ── Áreas (si están vacías) ────────────────────────────────────────
        if (DB::table('areas')->count() === 0) {
            DB::table('areas')->insert([
                ['description' => 'Administración',   'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['description' => 'Contabilidad',     'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['description' => 'Operaciones',      'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['description' => 'Recursos Humanos', 'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['description' => 'Sistemas',         'created_by' => 1, 'updated_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }
}
