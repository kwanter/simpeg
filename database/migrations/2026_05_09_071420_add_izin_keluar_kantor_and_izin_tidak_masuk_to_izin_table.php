<?php

use Illuminate\Database\Migrations\Migration;
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

            // SQLite uses CHECK constraints for enums — recreate with new values
            // 1. Create new table with expanded CHECK
            DB::statement('CREATE TABLE izin_new AS SELECT * FROM izin');
            DB::statement('DROP TABLE izin');
            DB::statement("CREATE TABLE izin (
                id integer primary key autoincrement,
                uuid varchar not null unique,
                pegawai_uuid varchar not null,
                no_surat_izin varchar,
                atasan_pimpinan_uuid varchar,
                pimpinan_uuid varchar,
                jenis_izin varchar not null check (jenis_izin in ('Izin Sakit','Izin Keperluan Keluarga','Izin Keperluan Pribadi','Izin Dinas Luar','Izin Setengah Hari','Izin Terlambat','Izin Pulang Cepat','Izin Lainnya','Izin Keluar Kantor','Izin Tidak Masuk Kerja')),
                tanggal_mulai date not null,
                tanggal_selesai date not null,
                jam_mulai time,
                jam_selesai time,
                jumlah_hari integer not null,
                alasan text not null,
                status varchar not null default 'Diajukan' check (status in ('Diajukan','Disetujui Atasan','Ditolak Atasan','Disetujui','Ditolak')),
                keterangan text,
                dokumen varchar,
                verifikasi_atasan varchar not null default 'Belum Diverifikasi' check (verifikasi_atasan in ('Belum Diverifikasi','Disetujui','Ditolak')),
                verifikasi_pimpinan varchar not null default 'Belum Diverifikasi' check (verifikasi_pimpinan in ('Belum Diverifikasi','Disetujui','Ditolak')),
                tanggal_verifikasi_atasan date,
                tanggal_verifikasi_pimpinan date,
                catatan_atasan text,
                catatan_pimpinan text,
                created_at datetime,
                updated_at datetime,
                foreign key (pegawai_uuid) references pegawai(uuid) on delete cascade
            )");
            DB::statement('INSERT INTO izin SELECT * FROM izin_new');
            DB::statement('DROP TABLE izin_new');

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE izin DROP CONSTRAINT IF EXISTS izin_jenis_izin_check');
            DB::statement("ALTER TABLE izin ADD CONSTRAINT izin_jenis_izin_check CHECK (jenis_izin IN (
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
            ))");

            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for izin enum migration.');
        }

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
        // SQLite stores enum as string natively — revert CHECK constraint
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE izin_new AS SELECT * FROM izin');
            DB::statement('DROP TABLE izin');
            DB::statement("CREATE TABLE izin (
                id integer primary key autoincrement,
                uuid varchar not null unique,
                pegawai_uuid varchar not null,
                no_surat_izin varchar,
                atasan_pimpinan_uuid varchar,
                pimpinan_uuid varchar,
                jenis_izin varchar not null check (jenis_izin in ('Izin Sakit','Izin Keperluan Keluarga','Izin Keperluan Pribadi','Izin Dinas Luar','Izin Setengah Hari','Izin Terlambat','Izin Pulang Cepat','Izin Lainnya')),
                tanggal_mulai date not null,
                tanggal_selesai date not null,
                jam_mulai time,
                jam_selesai time,
                jumlah_hari integer not null,
                alasan text not null,
                status varchar not null default 'Diajukan' check (status in ('Diajukan','Disetujui Atasan','Ditolak Atasan','Disetujui','Ditolak')),
                keterangan text,
                dokumen varchar,
                verifikasi_atasan varchar not null default 'Belum Diverifikasi' check (verifikasi_atasan in ('Belum Diverifikasi','Disetujui','Ditolak')),
                verifikasi_pimpinan varchar not null default 'Belum Diverifikasi' check (verifikasi_pimpinan in ('Belum Diverifikasi','Disetujui','Ditolak')),
                tanggal_verifikasi_atasan date,
                tanggal_verifikasi_pimpinan date,
                catatan_atasan text,
                catatan_pimpinan text,
                created_at datetime,
                updated_at datetime,
                foreign key (pegawai_uuid) references pegawai(uuid) on delete cascade
            )");
            DB::statement('INSERT INTO izin SELECT * FROM izin_new');
            DB::statement('DROP TABLE izin_new');

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE izin DROP CONSTRAINT IF EXISTS izin_jenis_izin_check');
            DB::statement("ALTER TABLE izin ADD CONSTRAINT izin_jenis_izin_check CHECK (jenis_izin IN (
                'Izin Sakit',
                'Izin Keperluan Keluarga',
                'Izin Keperluan Pribadi',
                'Izin Dinas Luar',
                'Izin Setengah Hari',
                'Izin Terlambat',
                'Izin Pulang Cepat',
                'Izin Lainnya'
            ))");

            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for izin enum rollback.');
        }

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
