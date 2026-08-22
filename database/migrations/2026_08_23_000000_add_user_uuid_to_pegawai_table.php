<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreignUuid('user_uuid')->nullable()->after('nip');
        });

        // Backfill by nip join (correlated subquery: portable across MySQL and SQLite,
        // idempotent via WHERE user_uuid IS NULL; nip is unique on pegawai and
        // app-validated unique on users, so at most one user matches).
        DB::update('UPDATE pegawai SET user_uuid = (SELECT u.uuid FROM users u WHERE u.nip = pegawai.nip LIMIT 1) WHERE user_uuid IS NULL');

        Schema::table('pegawai', function (Blueprint $table) {
            $table->unique('user_uuid');
            $table->foreign('user_uuid')
                ->references('uuid')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // SQLite cannot drop foreign keys (Blueprint throws); column drop
        // rebuilds the table without them anyway.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->dropForeign(['user_uuid']);
            });
        }

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropUnique(['user_uuid']);
            $table->dropColumn('user_uuid');
        });
    }
};
