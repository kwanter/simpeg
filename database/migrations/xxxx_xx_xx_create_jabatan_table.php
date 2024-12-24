<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jabatan', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->uuid('parent_uuid')->nullable();
            $table->timestamps();

            $table->foreign('parent_uuid')
                  ->references('uuid')
                  ->on('jabatan')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('jabatan');
    }
};
