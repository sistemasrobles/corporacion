<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Comprobantes de pago subidos por el AA al rendir (tipo desde masters main=15). */
    public function up(): void
    {
        Schema::create('refund_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('refund_id');
            $table->unsignedBigInteger('detail_id')->nullable();
            $table->string('type_file', 30)->nullable();     // value del master (main=15)
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->integer('file_size')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('issue_date')->nullable();
            $table->string('supplier', 150)->nullable();
            $table->string('document_number', 80)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();   // AA
            $table->timestamp('uploaded_at')->nullable();

            $table->foreign('refund_id')->references('id')->on('refund')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_files');
    }
};