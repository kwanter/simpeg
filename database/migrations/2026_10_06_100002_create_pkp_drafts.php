<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('pegawai_uuid')->constrained('pegawai', 'uuid')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('version');
            $table->uuid('official_revision_uuid')->nullable();
            $table->uuid('pending_revision_uuid')->nullable();
            $table->timestamp('first_submitted_at', 6)->nullable();
            $table->timestamp('deadline_snapshot_at', 6)->nullable();
            $table->string('lateness', 24)->nullable();
            $table->timestamps();
            $table->unique(['pegawai_uuid', 'year', 'month'], 'pkp_pegawai_period_unique');
            $table->index(['year', 'month'], 'pkp_period_index');
        });

        Schema::create('pkp_deadlines', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamp('deadline_at', 6)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['year', 'month'], 'pkp_deadlines_period_unique');
        });

        Schema::create('pkp_revisions', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('pkp_uuid')->constrained('pkp', 'uuid')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('source', 16);
            $table->string('state', 24);
            $table->foreignUuid('assessor_pegawai_uuid')->nullable()->constrained('pegawai', 'uuid')->restrictOnDelete();
            $table->foreignUuid('inspector_pegawai_uuid')->nullable()->constrained('pegawai', 'uuid')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->uuid('current_submission_uuid')->nullable();
            $table->uuid('approved_submission_uuid')->nullable();
            $table->json('approved_assessor_snapshot')->nullable();
            $table->uuid('source_document_uuid')->nullable();
            $table->uuid('final_document_uuid')->nullable();
            $table->uuid('pending_final_document_uuid')->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['pkp_uuid', 'number'], 'pkp_revisions_number_unique');
        });

        Schema::table('pkp', function (Blueprint $table): void {
            $table->foreign('official_revision_uuid', 'pkp_official_revision_fk')
                ->references('uuid')->on('pkp_revisions')->restrictOnDelete();
            $table->foreign('pending_revision_uuid', 'pkp_pending_revision_fk')
                ->references('uuid')->on('pkp_revisions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pkp', function (Blueprint $table): void {
            $table->dropForeign('pkp_official_revision_fk');
            $table->dropForeign('pkp_pending_revision_fk');
        });

        Schema::dropIfExists('pkp_revisions');
        Schema::dropIfExists('pkp_deadlines');
        Schema::dropIfExists('pkp');
    }
};
