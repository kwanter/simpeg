<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pegawai', 'tanggal_masuk')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->date('tanggal_masuk')->nullable()->after('status_pegawai');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pegawai', 'tanggal_masuk')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->dropColumn('tanggal_masuk');
            });
        }
    }
};
