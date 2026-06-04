<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->default('0');
            $table->integer('status')->default(0);
            $table->string('title', 250)->default('0');
            $table->unsignedBigInteger('type_id')->default(0);
            $table->string('format_id', 2)->default('0');
            $table->integer('user_responsible')->default(0);
            $table->string('motive_cancelation', 250)->default('0');
            $table->string('motive_observation', 250)->default('0');
            $table->integer('created_by')->default(0);
            $table->integer('updated_by')->default(0);
            $table->timestamps();

            $table->foreign('type_id')->references('id')->on('types')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
