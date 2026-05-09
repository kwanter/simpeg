<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite compatibility helper
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('IFNULL', function ($expr, $default) {
                return $default !== null ? $default : $expr;
            });

            // SQLite stores enum as string natively — no schema change needed.
            // The enum values are enforced at app level via validation.
            return;
        }

        // For MySQL: ALTER the enum to include new values
        DB::statement("ALTER TABLE izin MODIFY COLUMN jenis_izin ENUM(
            'Izin Sakit',
            'Izin Keperluan Keluarga',
            'Izin Keperluan Pribadi',
            'Izin Dinas Luar',
            'Izin Setengah Hari',
            'Izin Terlambat',
            'Izin Pulang Cepat',
            'Izin Lainnya',
            'Izin Keluar Kantor',
            'Izin Tidak Masuk Kerja'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite stores enum as string natively — no revert needed
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Revert MySQL to original 8 enum values
        DB::statement("ALTER TABLE izin MODIFY COLUMN jenis_izin ENUM(
            'Izin Sakit',
            'Izin Keperluan Keluarga',
            'Izin Keperluan Pribadi',
            'Izin Dinas Luar',
            'Izin Setengah Hari',
            'Izin Terlambat',
            'Izin Pulang Cepat',
            'Izin Lainnya'
        ) NOT NULL");
    }
};
