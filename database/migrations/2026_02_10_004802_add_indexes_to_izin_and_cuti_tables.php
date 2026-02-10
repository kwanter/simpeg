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
        Schema::table('izin', function (Blueprint $table) {
            $table->index('status');
            $table->index('atasan_pimpinan_uuid');
            $table->index('pimpinan_uuid');
        });

        Schema::table('cuti', function (Blueprint $table) {
            $table->index('status');
            $table->index('jenis_cuti');
            $table->index('pegawai_uuid');
        });

        Schema::table('hari_libur', function (Blueprint $table) {
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['atasan_pimpinan_uuid']);
            $table->dropIndex(['pimpinan_uuid']);
        });

        Schema::table('cuti', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['jenis_cuti']);
            $table->dropIndex(['pegawai_uuid']);
        });

        Schema::table('hari_libur', function (Blueprint $table) {
            $table->dropIndex(['tanggal']);
        });
    }
};
