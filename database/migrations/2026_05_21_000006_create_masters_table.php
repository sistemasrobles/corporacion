<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->nullable();
            $table->string('description', 50)->nullable();
            $table->string('description_min', 50)->nullable();
            $table->string('value', 50)->nullable();
            $table->string('value2', 50)->nullable();
            $table->integer('main')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masters');
    }
};
