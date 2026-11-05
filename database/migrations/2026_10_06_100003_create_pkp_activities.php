<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkp_indicators', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('revision_uuid')->constrained('pkp_revisions', 'uuid')->cascadeOnDelete();
            $table->text('title')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['revision_uuid', 'position'], 'pkp_indicators_position_unique');
        });

        Schema::create('pkp_activities', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('indicator_uuid')->constrained('pkp_indicators', 'uuid')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description')->nullable();
            $table->string('unit', 100)->nullable();
            $table->decimal('target_credit', 14, 6)->nullable();
            $table->decimal('target_quantity', 14, 4)->nullable();
            $table->decimal('target_quality', 10, 4)->nullable();
            $table->decimal('realized_quantity', 14, 4)->nullable();
            $table->timestamps();
            $table->unique(['indicator_uuid', 'position'], 'pkp_activities_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkp_activities');
        Schema::dropIfExists('pkp_indicators');
    }
};
