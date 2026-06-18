<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders_events', function (Blueprint $table) {
            $table->id();
            // Referencias de la orden (FK lógicas)
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_quota_id')->nullable();
            // Datos genéricos del evento
            $table->string('event_type', 50)->nullable();   // tipo de evento (extensible)
            $table->string('description', 255)->nullable();  // descripción (ej. N° de operación del abono)
            $table->string('file')->nullable();              // ruta del fichero (ej. constancia del abono)
            // Auditoría
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'order_quota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_events');
    }
};