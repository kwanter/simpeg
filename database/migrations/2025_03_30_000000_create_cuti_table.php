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
        // Schema::dropIfExists('cuti');
        Schema::create('cuti', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('pegawai_uuid')->constrained('pegawai', 'uuid')->onDelete('cascade');
            $table->string('no_surat_cuti')->nullable();
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
            $table->timestamp('tanggal_verifikasi')->nullable();
            $table->string('status_verifikator')->nullable(); // Disetujui, Ditolak

            // Pimpinan fields
            $table->text('catatan_pimpinan')->nullable();
            $table->foreignUuid('pimpinan_uuid')->nullable()->constrained('pegawai', 'uuid')->nullOnDelete();
            $table->timestamp('tanggal_verifikasi_pimpinan')->nullable();
            $table->string('status_pimpinan')->nullable(); // Disetujui, Ditolak

            // Atasan Pimpinan fields - removed after clauses
            $table->string('status_atasan_pimpinan')->nullable();
            $table->text('catatan_atasan_pimpinan')->nullable();
            $table->uuid('atasan_pimpinan_uuid')->nullable();
            $table->timestamp('tanggal_verifikasi_atasan_pimpinan')->nullable();

            // Dokumen fields
            $table->string('dokumen')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Add softDeletes directly
        });

        // Now add the tracking columns
        Schema::table('cuti', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('uuid');
            $table->string('created_by_username')->nullable()->after('created_by');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->string('updated_by_username')->nullable()->after('updated_by');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->string('deleted_by_username')->nullable()->after('deleted_by');
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
