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
        Schema::create('izin', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('pegawai_uuid');
            $table->string('no_surat_izin')->nullable();  // Added no_surat_izin field
            $table->uuid('atasan_pimpinan_uuid')->nullable();
            $table->uuid('pimpinan_uuid')->nullable();
            $table->enum('jenis_izin', [
                'Izin Sakit',
                'Izin Keperluan Keluarga',
                'Izin Keperluan Pribadi',
                'Izin Dinas Luar',
                'Izin Setengah Hari',
                'Izin Terlambat',
                'Izin Pulang Cepat',
                'Izin Lainnya'
            ]);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->integer('jumlah_hari');
            $table->text('alasan');
            $table->enum('status', ['Diajukan', 'Disetujui Atasan', 'Ditolak Atasan', 'Disetujui', 'Ditolak'])->default('Diajukan');
            $table->text('keterangan')->nullable();
            $table->string('dokumen')->nullable();
            $table->enum('verifikasi_atasan', ['Belum Diverifikasi', 'Disetujui', 'Ditolak'])->default('Belum Diverifikasi');
            $table->enum('verifikasi_pimpinan', ['Belum Diverifikasi', 'Disetujui', 'Ditolak'])->default('Belum Diverifikasi');
            $table->date('tanggal_verifikasi_atasan')->nullable();
            $table->date('tanggal_verifikasi_pimpinan')->nullable();
            $table->text('catatan_atasan')->nullable();
            $table->text('catatan_pimpinan')->nullable();
            $table->timestamps();

            $table->foreign('pegawai_uuid')->references('uuid')->on('pegawai')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izin');
    }
};
