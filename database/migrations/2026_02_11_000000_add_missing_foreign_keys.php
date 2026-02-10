<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('izin', function (Blueprint $table) {
            $table->foreign('atasan_pimpinan_uuid')
                ->references('uuid')
                ->on('pegawai')
                ->nullOnDelete();

            $table->foreign('pimpinan_uuid')
                ->references('uuid')
                ->on('pegawai')
                ->nullOnDelete();
        });

        Schema::table('cuti', function (Blueprint $table) {
            $table->foreign('atasan_pimpinan_uuid')
                ->references('uuid')
                ->on('pegawai')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('izin', function (Blueprint $table) {
            $table->dropForeign(['atasan_pimpinan_uuid']);
            $table->dropForeign(['pimpinan_uuid']);
        });

        Schema::table('cuti', function (Blueprint $table) {
            $table->dropForeign(['atasan_pimpinan_uuid']);
        });
    }
};
