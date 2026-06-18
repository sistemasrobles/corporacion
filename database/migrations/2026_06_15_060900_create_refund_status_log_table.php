<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Auditoría de todos los cambios de estado de cada orden (línea de tiempo). */
    public function up(): void
    {
        Schema::create('refund_status_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('refund_id');
            $table->smallInteger('from_status')->nullable();
            $table->smallInteger('to_status');
            $table->unsignedBigInteger('changed_by');
            $table->timestamp('changed_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreign('refund_id')->references('id')->on('refund')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_status_log');
    }
};