<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('address', 250)->nullable()->after('name');
            $table->string('district', 100)->nullable()->after('address');
            $table->string('contact', 100)->nullable()->after('district');
            $table->string('phone', 50)->nullable()->after('contact');
            $table->string('email', 100)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['address', 'district', 'contact', 'phone', 'email']);
        });
    }
};
