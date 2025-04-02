<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cuti_balances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('pegawai_uuid');
            $table->foreign('pegawai_uuid')->references('uuid')->on('pegawai')->onDelete('cascade');
            $table->integer('year');
            $table->integer('total_days')->default(12); // Default annual leave is 12 days
            $table->integer('used_days')->default(0);
            $table->integer('carried_over')->default(0); // Days carried over from previous year
            $table->timestamps();

            // Ensure each employee has only one record per year
            $table->unique(['pegawai_uuid', 'year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cuti_balances');
    }
};