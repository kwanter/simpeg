<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp_documents', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('revision_uuid')->constrained('pkp_revisions', 'uuid')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('storage_path', 512)->unique();
            $table->string('original_name', 255);
            $table->string('detected_mime', 127);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count');
            $table->string('validator_version', 64);
            $table->boolean('declared_signed');
            $table->foreignUuid('uploaded_by_user_uuid')->constrained('users', 'uuid')->restrictOnDelete();
            $table->timestamp('uploaded_at', 6);
            $table->uuid('replacement_of_document_uuid')->nullable();
            $table->boolean('content_unchanged_declared')->nullable();
            $table->timestamps(6);
            $table->index(['revision_uuid', 'kind'], 'pkp_documents_revision_kind_index');
        });

        Schema::table('pkp_documents', function (Blueprint $table): void {
            $table->foreign('replacement_of_document_uuid', 'pkp_document_replacement_fk')
                ->references('uuid')->on('pkp_documents')->restrictOnDelete();
        });

        Schema::table('pkp_revisions', function (Blueprint $table): void {
            $table->foreign('source_document_uuid', 'pkp_source_document_fk')
                ->references('uuid')->on('pkp_documents')->restrictOnDelete();
            $table->foreign('final_document_uuid', 'pkp_final_document_fk')
                ->references('uuid')->on('pkp_documents')->restrictOnDelete();
            $table->foreign('pending_final_document_uuid', 'pkp_pending_final_document_fk')
                ->references('uuid')->on('pkp_documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pkp_revisions', function (Blueprint $table): void {
            $table->dropForeign('pkp_source_document_fk');
            $table->dropForeign('pkp_final_document_fk');
            $table->dropForeign('pkp_pending_final_document_fk');
        });
        Schema::table('pkp_documents', function (Blueprint $table): void {
            $table->dropForeign('pkp_document_replacement_fk');
        });
        Schema::dropIfExists('pkp_documents');
    }
};
