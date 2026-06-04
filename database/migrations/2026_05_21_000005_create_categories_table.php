<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('description', 50)->default('0');
            $table->unsignedBigInteger('format_id')->default(0);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('format_id')->references('id')->on('formats');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
