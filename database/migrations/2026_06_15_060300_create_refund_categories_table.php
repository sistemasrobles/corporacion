<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Catálogo de categorías de gasto (clasificación) de los requerimientos. */
    public function up(): void
    {
        Schema::create('refund_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $uid = DB::table('users')->min('id');   // primer usuario como creador del catálogo base
        $now = now();
        DB::table('refund_categories')->insert([
            ['code' => 'PASAJES',      'name' => 'Pasajes y viáticos',      'description' => 'Transporte, pasajes, movilidad',              'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PROVEEDOR',    'name' => 'Pago a proveedores',      'description' => 'Pagos por bienes o servicios de terceros',    'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EVENTO',       'name' => 'Eventos y actividades',   'description' => 'Gastos para eventos, reuniones, activaciones', 'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ALIMENTACION', 'name' => 'Alimentación',            'description' => 'Catering, almuerzos, refrigerios',            'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MATERIALES',   'name' => 'Materiales y suministros','description' => 'Útiles, insumos, materiales de oficina',      'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SERVICIOS',    'name' => 'Servicios generales',     'description' => 'Servicios varios no categorizados',           'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OTRO',         'name' => 'Otro',                    'description' => 'Categoría genérica para casos no listados',   'is_active' => true, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_categories');
    }
};