<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('keterangan')->nullable();
            $table->string('bonus')->nullable();
            $table->string('jenis_pemberian')->nullable();
            $table->string('jenis_promosi')->nullable();
            $table->string('min_deposite')->nullable();
            $table->string('max_deposite')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->string('turnover')->nullable();
            $table->text('body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['keterangan', 'bonus', 'jenis_pemberian', 'jenis_promosi', 'min_deposite', 'max_deposite', 'tanggal_mulai', 'tanggal_akhir', 'turnover', 'body']);
        });
    }
};
