<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sedes')) {
            Schema::create('sedes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->unique();
                $table->string('descripcion', 255)->nullable();
                $table->timestamps();
            });

            DB::table('sedes')->insert([
                ['nombre' => 'DERBY', 'descripcion' => 'Sede Derby', 'created_at' => now(), 'updated_at' => now()],
                ['nombre' => 'OPB', 'descripcion' => 'Sede OPB', 'created_at' => now(), 'updated_at' => now()],
                ['nombre' => 'ENCALADA', 'descripcion' => 'Sede Encalada', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (Schema::hasTable('order_details') && !Schema::hasColumn('order_details', 'sede_id')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->unsignedBigInteger('sede_id')->nullable()->after('area_id');
                $table->foreign('sede_id')->references('id')->on('sedes')->onDelete('set null');
            });
        }

        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'amount_ref')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('amount_ref');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                if (Schema::hasColumn('order_details', 'sede_id')) {
                    $table->dropForeign(['sede_id']);
                    $table->dropColumn('sede_id');
                }
            });
        }
        if (Schema::hasTable('sedes')) {
            Schema::dropIfExists('sedes');
        }
    }
};
