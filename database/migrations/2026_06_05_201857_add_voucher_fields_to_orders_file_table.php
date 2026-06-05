<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            $table->string('document_number')->nullable()->after('type_file');
            $table->decimal('amount', 12, 2)->nullable()->after('document_number');
            $table->date('emission_date')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders_file', function (Blueprint $table) {
            $table->dropColumn(['document_number', 'amount', 'emission_date']);
        });
    }
};
