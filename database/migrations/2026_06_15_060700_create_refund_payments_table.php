<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vouchers de movimiento de dinero del requerimiento. payment_type diferencia:
     * ABONO_INICIAL (GF abona el fondo) · REEMBOLSO_TRABAJADOR (GF paga faltante al AA) ·
     * DEVOLUCION_EMPRESA (AA devuelve sobrante).
     */
    public function up(): void
    {
        Schema::create('refund_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('refund_id');
            $table->string('payment_type', 30);
            $table->decimal('amount', 12, 2);
            $table->date('payment_date')->nullable();
            $table->string('bank_origin', 80)->nullable();
            $table->string('bank_destination', 80)->nullable();
            $table->string('account_destination', 50)->nullable();
            $table->string('transaction_code', 100)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();   // GF o AF
            $table->timestamp('uploaded_at')->nullable();

            $table->foreign('refund_id')->references('id')->on('refund')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_payments');
    }
};