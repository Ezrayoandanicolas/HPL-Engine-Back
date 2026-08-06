<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->integer('type')->nullable();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->bigInteger('status_id')->nullable();
            $table->string('notes')->default('unread');
            $table->string('ref')->nullable();
            $table->string('img')->nullable();
            $table->string('bank_penerima')->nullable();
            $table->string('nama_penerima')->nullable();
            $table->string('nomer_penerima')->nullable();
            $table->string('accName')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};