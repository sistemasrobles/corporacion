<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cuentas bancarias de un beneficiario (varias por beneficiario). */
    public function up(): void
    {
        Schema::create('beneficiary_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('beneficiary_id');
            $table->string('bank', 80)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('cci', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('beneficiary_id')->references('id')->on('beneficiaries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_accounts');
    }
};