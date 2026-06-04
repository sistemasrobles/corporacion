<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_history', function (Blueprint $table) {
            $table->id();
            $table->string('from_user', 50)->default('0');
            $table->string('to_user', 50)->default('0');
            $table->integer('from_status')->default(0);
            $table->integer('to_status')->default(0);
            $table->string('coment', 50)->default('0');
            $table->unsignedBigInteger('order_id')->default(0);
            $table->integer('created_by')->default(0);
            $table->integer('updated_by')->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_history');
    }
};
