<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Catálogo de estados de la Orden de Requerimiento (fuente única de verdad). */
    public function up(): void
    {
        Schema::create('refund_status', function (Blueprint $table) {
            $table->smallInteger('id')->primary();      // ids fijos 0..10
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->text('description')->nullable();
            $table->string('generated_by', 20)->nullable();
            $table->string('visible_to', 50)->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        $now = now();
        DB::table('refund_status')->insert([
            ['id' => 0,  'code' => 'CREADO',             'name' => 'Creado',              'description' => 'Estado inicial de una orden de requerimiento',           'generated_by' => 'AA',        'visible_to' => 'AA',    'is_final' => false, 'created_at' => $now],
            ['id' => 1,  'code' => 'POR_REVISAR',        'name' => 'Por revisar',         'description' => 'Lo genera el AA al enviar, le aparece al GA',            'generated_by' => 'AA',        'visible_to' => 'GA',    'is_final' => false, 'created_at' => $now],
            ['id' => 2,  'code' => 'APROBADO',           'name' => 'Aprobado',            'description' => 'Lo genera el GA, le aparece al GF',                      'generated_by' => 'GA',        'visible_to' => 'GF',    'is_final' => false, 'created_at' => $now],
            ['id' => 3,  'code' => 'RECHAZADO',          'name' => 'Rechazado',           'description' => 'Lo genera el GA, fin de la orden',                       'generated_by' => 'GA',        'visible_to' => 'AA,GA', 'is_final' => true,  'created_at' => $now],
            ['id' => 4,  'code' => 'OBSERVADO',          'name' => 'Observado',           'description' => 'Lo genera el GA, AF o UC1, regresa al AA',               'generated_by' => 'GA,AF,UC1', 'visible_to' => 'AA',    'is_final' => false, 'created_at' => $now],
            ['id' => 5,  'code' => 'ABONADO',            'name' => 'Abonado',             'description' => 'Lo genera el GF, le aparece al AF y al GF',              'generated_by' => 'GF',        'visible_to' => 'AF,GF', 'is_final' => false, 'created_at' => $now],
            ['id' => 6,  'code' => 'CONSTANCIA_ADJUNTA', 'name' => 'Constancia adjunta',  'description' => 'Lo genera el AF o GF, le aparece al AA',                 'generated_by' => 'AF,GF',     'visible_to' => 'AA',    'is_final' => false, 'created_at' => $now],
            ['id' => 7,  'code' => 'RENDIDO',            'name' => 'Rendido',             'description' => 'Lo genera el AA al subir comprobantes, le aparece al AF', 'generated_by' => 'AA',       'visible_to' => 'AF',    'is_final' => false, 'created_at' => $now],
            ['id' => 8,  'code' => 'REEMBOLSO',          'name' => 'Reembolso',           'description' => 'Lo genera el AA cuando hay faltante, le aparece al GF',  'generated_by' => 'AA',        'visible_to' => 'GF',    'is_final' => false, 'created_at' => $now],
            ['id' => 9,  'code' => 'CONFORME',           'name' => 'Conforme',            'description' => 'Lo genera el AF, le aparece al UC1',                     'generated_by' => 'AF',        'visible_to' => 'UC1',   'is_final' => false, 'created_at' => $now],
            ['id' => 10, 'code' => 'CERRADO',            'name' => 'Cerrado',             'description' => 'Lo genera el UC1, cierre definitivo',                    'generated_by' => 'UC1',       'visible_to' => 'TODOS', 'is_final' => true,  'created_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_status');
    }
};