<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('masters')->where('id', 24)->delete();
    }

    public function down(): void
    {
        DB::table('masters')->insert([
            'id'          => 24,
            'main'        => 18,
            'description' => 'CONSTACIA DE ABONO',
            'value'       => '8',
        ]);
    }
};