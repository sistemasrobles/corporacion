<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Desactivar constraints temporalmente
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DISABLE TRIGGER ALL');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        // Columna uc_level en users
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('uc_level')->nullable()->after('user_type');
        });

        // Actualizar CIELO SANCHEZ (id=7) → UC level 1
        DB::table('users')->where('id', 7)->update(['user_type' => 'UC', 'uc_level' => 1]);

        // Crear PEDRO BARDALES → UC level 2
        DB::table('users')->insert([
            'name'       => 'PEDRO BARDALES',
            'email'      => 'pedro.bardales@empresa.com',
            'user_type'  => 'UC',
            'uc_level'   => 2,
            'password'   => Hash::make('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear ANNICK AGUILAR → UC level 3
        DB::table('users')->insert([
            'name'       => 'ANNICK AGUILAR',
            'email'      => 'annick.aguilar@empresa.com',
            'user_type'  => 'UC',
            'uc_level'   => 3,
            'password'   => Hash::make('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tipos de observación en masters (main=20)
        DB::table('masters')->insert([
            ['main' => 20, 'description' => 'Documentación incompleta',    'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['main' => 20, 'description' => 'Datos incorrectos',           'value' => '2', 'created_at' => now(), 'updated_at' => now()],
            ['main' => 20, 'description' => 'Monto no coincide',           'value' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['main' => 20, 'description' => 'Cuenta bancaria incorrecta',  'value' => '4', 'created_at' => now(), 'updated_at' => now()],
            ['main' => 20, 'description' => 'Falta de sustento',           'value' => '5', 'created_at' => now(), 'updated_at' => now()],
            ['main' => 20, 'description' => 'Error en código contable',    'value' => '6', 'created_at' => now(), 'updated_at' => now()],
            ['main' => 20, 'description' => 'Otros',                       'value' => '7', 'created_at' => now(), 'updated_at' => now()],
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ENABLE TRIGGER ALL');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        DB::table('users')->whereIn('email', ['pedro.bardales@empresa.com', 'annick.aguilar@empresa.com'])->delete();
        DB::table('users')->where('id', 7)->update(['user_type' => 'AA']);
        DB::table('masters')->where('main', 20)->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uc_level');
        });
    }
};