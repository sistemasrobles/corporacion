<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Áreas azules (14) → CCs azules (5): 200(47), 204(57), 207(64), 209(55), 210(59)
        $blueAreas = [1, 2, 3, 4, 5, 6, 7, 9, 11, 12, 13, 14, 16, 17];
        $blueCCs   = [47, 55, 57, 59, 64];

        // Áreas blancas (3) → CCs blancos (todos los demás)
        $whiteAreas = [8, 10, 15];
        $whiteCCs   = [35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46,
                       48, 49, 50, 51, 52, 53, 54, 56, 58, 60, 61, 62, 63, 65, 66, 67];

        $rows = [];

        foreach ($blueAreas as $areaId) {
            foreach ($blueCCs as $ccId) {
                $rows[] = ['area_id' => $areaId, 'cc_id' => $ccId,
                           'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach ($whiteAreas as $areaId) {
            foreach ($whiteCCs as $ccId) {
                $rows[] = ['area_id' => $areaId, 'cc_id' => $ccId,
                           'created_at' => $now, 'updated_at' => $now];
            }
        }

        // Desactivar constraints temporalmente
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE area_cost_center DISABLE TRIGGER ALL');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        DB::table('area_cost_center')->insert($rows);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE area_cost_center ENABLE TRIGGER ALL');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        DB::table('area_cost_center')->truncate();
    }
};