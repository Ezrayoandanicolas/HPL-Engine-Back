<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('ref');
            $table->string('payment_gateway')->nullable()->after('payment_method');
            $table->string('qris_trx_id')->nullable()->after('payment_gateway');
            $table->text('qris_payload')->nullable()->after('qris_trx_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_gateway', 'qris_trx_id', 'qris_payload']);
        });
    }
};
