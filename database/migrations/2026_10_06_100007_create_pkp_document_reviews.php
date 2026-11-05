<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp_document_reviews', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('document_uuid')->unique()->constrained('pkp_documents', 'uuid')->restrictOnDelete();
            $table->foreignUuid('actor_user_uuid')->constrained('users', 'uuid')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->string('basis', 32);
            $table->foreignUuid('approved_submission_uuid')->constrained('pkp_submissions', 'uuid')->restrictOnDelete();
            $table->foreignUuid('replacement_base_document_uuid')->nullable()->constrained('pkp_documents', 'uuid')->restrictOnDelete();
            $table->boolean('matches_approved_content')->nullable();
            $table->timestamp('decided_at', 6);
            $table->timestamp('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkp_document_reviews');
    }
};
