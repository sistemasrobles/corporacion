<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Conceptos de gasto dentro de un requerimiento (estimado al crear, real al rendir). */
    public function up(): void
    {
        Schema::create('refund_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('refund_id');
            $table->string('description', 255);
            $table->string('supplier', 150)->nullable();
            $table->unsignedInteger('category_id')->nullable();   // refund_categories
            $table->decimal('estimated_amount', 12, 2)->default(0);
            $table->decimal('actual_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('refund_id')->references('id')->on('refund')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_details');
    }
};