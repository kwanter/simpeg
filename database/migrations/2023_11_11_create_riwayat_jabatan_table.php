<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('riwayat_jabatan')) {
            Schema::create('riwayat_jabatan', function (Blueprint $table) {
                $table->uuid('uuid')->primary();
                $table->uuid('pegawai_uuid');
                $table->uuid('jabatan_uuid');
                $table->string('satuan_kerja');
                $table->date('tanggal_mulai');
                $table->text('keterangan')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->foreign('pegawai_uuid')->references('uuid')->on('pegawai')->onDelete('cascade');
                $table->foreign('jabatan_uuid')->references('uuid')->on('jabatan')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('riwayat_jabatan');
    }
};
