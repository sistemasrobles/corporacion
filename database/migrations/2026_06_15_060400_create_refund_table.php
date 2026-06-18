<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cabecera de la Orden de Requerimiento. */
    public function up(): void
    {
        Schema::create('refund', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();           // Ej: REQ-2026-0001
            $table->smallInteger('status')->default(0);     // → refund_status

            // Cabecera (formulario)
            $table->unsignedBigInteger('company_id')->nullable();      // companies
            $table->unsignedBigInteger('area_id')->nullable();         // areas
            $table->unsignedBigInteger('cost_center_id')->nullable();  // cost_centers
            $table->unsignedInteger('category_id')->nullable();        // refund_categories
            $table->string('currency', 10)->nullable();
            $table->string('title', 250);
            $table->text('purpose');                        // justificación

            // Beneficiario (+ snapshot de la cuenta destino elegida)
            $table->unsignedBigInteger('beneficiary_id')->nullable();
            $table->unsignedBigInteger('beneficiary_account_id')->nullable();
            $table->string('beneficiary_account', 50)->nullable();
            $table->string('beneficiary_bank', 80)->nullable();

            // Montos
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->decimal('rendered_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();

            // Auditoría del flujo
            $table->unsignedBigInteger('created_by');                  // AA
            $table->unsignedBigInteger('approved_by')->nullable();     // GA
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();     // GA
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();       // UC1
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
            $table->index('beneficiary_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund');
    }
};