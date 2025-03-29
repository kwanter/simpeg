<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tracking columns to cuti table
        Schema::table('cuti', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('uuid');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->uuid('deleted_by')->nullable()->after('updated_by');
            $table->string('created_by_username')->nullable()->after('created_by');
            $table->string('updated_by_username')->nullable()->after('updated_by');
            $table->string('deleted_by_username')->nullable()->after('deleted_by');
            $table->foreign('created_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('uuid')->on('users')->onDelete('set null');
        });

        // Add tracking columns to riwayat_jabatan table
        Schema::table('riwayat_jabatan', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('uuid');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->string('created_by_username')->nullable()->after('created_by');
            $table->string('updated_by_username')->nullable()->after('updated_by');
            $table->string('deleted_by_username')->nullable()->after('deleted_by');
            $table->foreign('created_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('uuid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cuti', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn([
                'created_by', 'created_by_username',
                'updated_by', 'updated_by_username',
                'deleted_by', 'deleted_by_username'
            ]);
        });

        Schema::table('riwayat_jabatan', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn([
                'created_by', 'created_by_username',
                'updated_by', 'updated_by_username',
                'deleted_by', 'deleted_by_username'
            ]);
        });
    }
};