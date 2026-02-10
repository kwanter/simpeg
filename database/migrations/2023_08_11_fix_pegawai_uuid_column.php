<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Disable foreign key checks for MySQL only (skip for SQLite)
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            // Drop the foreign key constraint
            if (Schema::hasTable('riwayat_pangkat')) {
                $foreignKeys = DB::select("
                    SELECT constraint_name
                    FROM information_schema.table_constraints
                    WHERE constraint_type = 'FOREIGN KEY'
                    AND table_name = 'riwayat_pangkat'
                    AND constraint_name = 'riwayat_pangkat_pegawai_uuid_foreign'
                    AND table_schema = DATABASE()
                ");

                if (! empty($foreignKeys)) {
                    DB::statement('ALTER TABLE riwayat_pangkat DROP FOREIGN KEY riwayat_pangkat_pegawai_uuid_foreign');
                }
            }

            // Recreate the foreign key constraint
            if (Schema::hasTable('riwayat_pangkat') && Schema::hasTable('pegawai')) {
                DB::statement('ALTER TABLE riwayat_pangkat ADD CONSTRAINT riwayat_pangkat_pegawai_uuid_foreign FOREIGN KEY (pegawai_uuid) REFERENCES pegawai(uuid) ON DELETE CASCADE');
            }

            // Check if riwayat_jabatan table exists before adding columns
            if (Schema::hasTable('riwayat_jabatan')) {
                // Add columns individually with checks
                if (! Schema::hasColumn('riwayat_jabatan', 'created_by')) {
                    Schema::table('riwayat_jabatan', function (Blueprint $table) {
                        $table->uuid('created_by')->nullable()->after('uuid');
                    });
                }

                if (! Schema::hasColumn('riwayat_jabatan', 'updated_by')) {
                    Schema::table('riwayat_jabatan', function (Blueprint $table) {
                        $table->uuid('updated_by')->nullable()->after('updated_at');
                    });
                }

                if (! Schema::hasColumn('riwayat_jabatan', 'deleted_by')) {
                    Schema::table('riwayat_jabatan', function (Blueprint $table) {
                        $table->uuid('deleted_by')->nullable()->after('deleted_at');
                    });
                }

                if (! Schema::hasColumn('riwayat_jabatan', 'created_by_username')) {
                    Schema::table('riwayat_jabatan', function (Blueprint $table) {
                        $table->string('created_by_username')->nullable()->after('created_by');
                    });
                }

                if (! Schema::hasColumn('riwayat_jabatan', 'updated_by_username')) {
                    Schema::table('riwayat_jabatan', function (Blueprint $table) {
                        $table->string('updated_by_username')->nullable()->after('updated_by');
                    });
                }

                if (! Schema::hasColumn('riwayat_jabatan', 'deleted_by_username')) {
                    Schema::table('riwayat_jabatan', function (Blueprint $table) {
                        $table->string('deleted_by_username')->nullable()->after('deleted_by');
                    });
                }
            } else {
                // Create the riwayat_jabatan table if it doesn't exist
                Schema::create('riwayat_jabatan', function (Blueprint $table) {
                    $table->id();
                    $table->uuid('uuid');
                    $table->uuid('created_by')->nullable();
                    $table->string('created_by_username')->nullable();
                    $table->uuid('pegawai_uuid');
                    $table->string('nama_jabatan');
                    $table->string('unit_kerja')->nullable();
                    $table->date('tmt_jabatan');
                    $table->string('no_sk')->nullable();
                    $table->date('tanggal_sk')->nullable();
                    $table->string('pejabat_penetap')->nullable();
                    $table->string('dokumen')->nullable();
                    $table->timestamps();
                    $table->uuid('updated_by')->nullable();
                    $table->string('updated_by_username')->nullable();
                    $table->softDeletes();
                    $table->uuid('deleted_by')->nullable();
                    $table->string('deleted_by_username')->nullable();

                    $table->foreign('pegawai_uuid')->references('uuid')->on('pegawai')->onDelete('cascade');
                });
            }

        } finally {
            // Re-enable foreign key checks for MySQL only
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the tracking columns from riwayat_jabatan if they exist
        if (Schema::hasTable('riwayat_jabatan')) {
            Schema::table('riwayat_jabatan', function (Blueprint $table) {
                $columns = [
                    'created_by', 'updated_by', 'deleted_by',
                    'created_by_username', 'updated_by_username', 'deleted_by_username',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('riwayat_jabatan', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
