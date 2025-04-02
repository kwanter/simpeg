<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tracking columns to pegawai table
        Schema::table('pegawai', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('uuid');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->foreign('created_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('uuid')->on('users')->onDelete('set null');
        });

        // Add tracking columns to riwayat_pangkat table
        Schema::table('riwayat_pangkat', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('uuid');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->foreign('created_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('uuid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
        });

        Schema::table('riwayat_pangkat', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
        });
    }
};