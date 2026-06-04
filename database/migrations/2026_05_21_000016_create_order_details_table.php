<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->date('required_date');
            $table->decimal('suggested_amount', 20, 6)->default(0);
            $table->string('justification', 250)->default('0');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('tc', 20, 6)->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('cc_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->integer('payment_id')->nullable();
            $table->integer('condition_payment')->nullable();
            $table->integer('quotas')->nullable();
            $table->date('expiration_date')->nullable();
            $table->unsignedInteger('grabable')->nullable();
            $table->decimal('discount', 20, 6)->nullable();
            $table->decimal('igv', 20, 6)->nullable();
            $table->decimal('sub_total', 20, 6)->nullable();
            $table->decimal('total', 20, 6)->nullable();
            $table->decimal('amount_neto', 20, 6)->nullable();
            $table->string('observation', 250)->nullable();
            $table->string('period', 6)->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('area_id')->references('id')->on('areas');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('cc_id')->references('id')->on('cost_centers');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
