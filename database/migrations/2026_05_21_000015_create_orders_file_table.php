<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders_file', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->integer('type_file')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('path', 250)->nullable();
            $table->integer('principal')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_file');
    }
};
