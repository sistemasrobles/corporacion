<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado del proveedor: active = 1 (activo) / 0 (inactivo). El "eliminar" del módulo
     * de Proveedores en realidad inactiva (soft-delete), conservando el histórico. Los
     * proveedores existentes quedan activos por el default.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};