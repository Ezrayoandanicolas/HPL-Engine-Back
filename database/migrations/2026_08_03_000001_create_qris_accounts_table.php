<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->default('saweria');
            $table->string('name')->nullable();
            $table->text('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transaksis', function (Blueprint $table) {
            $table->unsignedBigInteger('qris_account_id')->nullable()->after('qris_payload');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn('qris_account_id');
        });
        Schema::dropIfExists('qris_accounts');
    }
};
