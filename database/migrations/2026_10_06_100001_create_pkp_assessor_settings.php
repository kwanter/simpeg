<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp_assessor_settings', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('pegawai_uuid')->unique()->constrained('pegawai', 'uuid')->restrictOnDelete();
            $table->foreignUuid('default_assessor_uuid')->nullable()->constrained('pegawai', 'uuid')->restrictOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('pkp_allowed_assessors', function (Blueprint $table): void {
            $table->foreignUuid('settings_uuid')->constrained('pkp_assessor_settings', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('assessor_pegawai_uuid')->constrained('pegawai', 'uuid')->restrictOnDelete();
            $table->primary(['settings_uuid', 'assessor_pegawai_uuid'], 'pkp_allowed_assessors_primary');
        });

        Schema::create('pkp_configuration_events', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->string('kind', 32);
            $table->uuid('configuration_uuid');
            $table->foreignUuid('actor_user_uuid')->constrained('users', 'uuid')->restrictOnDelete();
            $table->json('before_json')->nullable();
            $table->json('after_json');
            $table->timestamp('created_at', 6);
            $table->index(['kind', 'configuration_uuid'], 'pkp_configuration_events_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkp_configuration_events');
        Schema::dropIfExists('pkp_allowed_assessors');
        Schema::dropIfExists('pkp_assessor_settings');
    }
};
