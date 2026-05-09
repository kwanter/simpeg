<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Register UUID() function for SQLite so DEFAULT (UUID()) works
        // in tests. MySQL has UUID() built-in.
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('UUID', function () {
                return Str::orderedUuid()->toString();
            });
        }

        Schema::create('revisions', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->string('revisionable_type');
            $table->string('revisionable_id');
            $table->uuid('user_id')->nullable();
            $table->string('key');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index(['revisionable_id', 'revisionable_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('revisions');
    }
}
