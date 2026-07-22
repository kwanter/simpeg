<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            if (! Schema::hasColumn('riwayat_jabatan', $column)) {
                Schema::table('riwayat_jabatan', function (Blueprint $table) use ($column): void {
                    $table->uuid($column)->nullable();
                });
            }
        }

        foreach (['created_by_username', 'updated_by_username', 'deleted_by_username'] as $column) {
            if (! Schema::hasColumn('riwayat_jabatan', $column)) {
                Schema::table('riwayat_jabatan', function (Blueprint $table) use ($column): void {
                    $table->string($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'created_by',
            'updated_by',
            'deleted_by',
            'created_by_username',
            'updated_by_username',
            'deleted_by_username',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('riwayat_jabatan', $column)) {
                Schema::table('riwayat_jabatan', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
