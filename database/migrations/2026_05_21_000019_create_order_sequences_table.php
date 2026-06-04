<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->integer('year_code');
            $table->string('order_type', 2);
            $table->integer('last_number')->default(0);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->primary(['year_code', 'order_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sequences');
    }
};
