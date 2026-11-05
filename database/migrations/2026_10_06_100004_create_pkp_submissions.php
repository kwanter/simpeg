<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp_submissions', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('revision_uuid')->constrained('pkp_revisions', 'uuid')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('state', 32);
            $table->json('input_snapshot');
            $table->json('identity_snapshot');
            $table->json('assessor_snapshot');
            $table->uuid('source_document_uuid')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->string('calculation_version', 32)->nullable();
            $table->json('score_snapshot')->nullable();
            $table->char('payload_sha256', 64);
            $table->char('identity_sha256', 64);
            $table->timestamp('submitted_at', 6);
            $table->timestamps(6);
            $table->unique(['revision_uuid', 'number'], 'pkp_submissions_attempt_unique');
        });

        Schema::create('pkp_events', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('pkp_uuid')->constrained('pkp', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('revision_uuid')->nullable()->constrained('pkp_revisions', 'uuid')->restrictOnDelete();
            $table->foreignUuid('actor_user_uuid')->constrained('users', 'uuid')->restrictOnDelete();
            $table->string('event_type', 48);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->index(['pkp_uuid', 'occurred_at'], 'pkp_events_history_index');
        });

        Schema::table('pkp_revisions', function (Blueprint $table): void {
            $table->foreign('current_submission_uuid', 'pkp_current_submission_fk')
                ->references('uuid')->on('pkp_submissions')->restrictOnDelete();
            $table->foreign('approved_submission_uuid', 'pkp_approved_submission_fk')
                ->references('uuid')->on('pkp_submissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pkp_revisions', function (Blueprint $table): void {
            $table->dropForeign('pkp_current_submission_fk');
            $table->dropForeign('pkp_approved_submission_fk');
        });

        Schema::dropIfExists('pkp_events');
        Schema::dropIfExists('pkp_submissions');
    }
};
