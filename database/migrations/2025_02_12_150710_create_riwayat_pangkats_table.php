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
        Schema::create('riwayat_pangkat', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('pegawai_uuid');
            $table->string('pangkat_golongan');
            $table->date('tmt');
            $table->string('nomor_sk');
            $table->date('tanggal_sk');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('pegawai_uuid')->references('uuid')->on('pegawai')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_pangkats');
    }
};
