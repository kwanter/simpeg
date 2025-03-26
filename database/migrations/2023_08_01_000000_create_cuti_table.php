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
        Schema::create('cuti', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('pegawai_uuid')->constrained('pegawai', 'uuid')->onDelete('cascade');
            $table->string('jenis_cuti');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('lama_cuti');
            $table->text('alasan');
            $table->string('alamat_selama_cuti')->nullable();
            $table->string('no_hp_selama_cuti')->nullable();
            $table->string('status')->default('Pending'); // Pending, Disetujui Verifikator, Ditolak Verifikator, Disetujui Pimpinan, Ditolak Pimpinan

            // Verifikator fields
            $table->text('catatan_verifikator')->nullable();
            $table->foreignUuid('verifikator_uuid')->nullable()->constrained('pegawai', 'uuid')->nullOnDelete();
            $table->date('tanggal_verifikasi')->nullable();
            $table->string('status_verifikator')->nullable(); // Disetujui, Ditolak

            // Pimpinan fields
            $table->text('catatan_pimpinan')->nullable();
            $table->foreignUuid('pimpinan_uuid')->nullable()->constrained('pegawai', 'uuid')->nullOnDelete();
            $table->date('tanggal_verifikasi_pimpinan')->nullable();
            $table->string('status_pimpinan')->nullable(); // Disetujui, Ditolak

            $table->string('dokumen')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuti');
    }
};