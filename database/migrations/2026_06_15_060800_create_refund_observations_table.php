<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Observaciones/comentarios en los cambios de estado (motivo de OBSERVADO, etc.). */
    public function up(): void
    {
        Schema::create('refund_observations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('refund_id');
            $table->smallInteger('from_status');
            $table->smallInteger('to_status');
            $table->unsignedBigInteger('observed_by');
            $table->string('role', 20);
            $table->text('comment');
            $table->timestamp('created_at')->nullable();

            $table->foreign('refund_id')->references('id')->on('refund')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_observations');
    }
};