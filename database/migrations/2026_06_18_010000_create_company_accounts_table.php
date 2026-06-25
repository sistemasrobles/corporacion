<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('currency', 3)->default('PEN');
            $table->string('bank', 100);
            $table->string('account_number', 100);
            $table->string('cci', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // Backfill: la cuenta única actual de cada empresa pasa a ser su cuenta primaria.
        DB::table('companies')
            ->whereNotNull('source_account_number')
            ->where('source_account_number', '<>', '')
            ->orderBy('id')
            ->get()
            ->each(function ($c) {
                DB::table('company_accounts')->insert([
                    'company_id'     => $c->id,
                    'currency'       => 'PEN',
                    'bank'           => $c->source_bank ?: '—',
                    'account_number' => $c->source_account_number,
                    'cci'            => $c->source_cci,
                    'is_primary'     => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_accounts');
    }
};