<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('source_bank', 100)->nullable()->after('ruc');
            $table->string('source_account_number', 100)->nullable()->after('source_bank');
            $table->string('source_cci', 100)->nullable()->after('source_account_number');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->string('source_account', 200)->nullable()->after('codigo_banco');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['source_bank', 'source_account_number', 'source_cci']);
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('source_account');
        });
    }
};