<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp_decisions', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('submission_uuid')->unique()->constrained('pkp_submissions', 'uuid')->restrictOnDelete();
            $table->foreignUuid('actor_user_uuid')->constrained('users', 'uuid')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->timestamp('decided_at', 6);
            $table->timestamp('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkp_decisions');
    }
};
