<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('created_by_username')->nullable()->after('created_by');
            $table->string('updated_by_username')->nullable()->after('updated_by');
            $table->string('deleted_by_username')->nullable()->after('deleted_by');
        });

        Schema::table('riwayat_pangkat', function (Blueprint $table) {
            $table->string('created_by_username')->nullable()->after('created_by');
            $table->string('updated_by_username')->nullable()->after('updated_by');
            $table->string('deleted_by_username')->nullable()->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['created_by_username', 'updated_by_username', 'deleted_by_username']);
        });

        Schema::table('riwayat_pangkat', function (Blueprint $table) {
            $table->dropColumn(['created_by_username', 'updated_by_username', 'deleted_by_username']);
        });
    }
};