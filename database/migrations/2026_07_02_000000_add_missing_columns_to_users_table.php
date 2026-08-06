<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->string('role', 50)->default('member')->after('email');
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('bank', 255)->nullable();
            $table->text('informasi')->nullable();
            $table->string('pembayaran', 50)->default('bank');
            $table->string('country', 50)->nullable();
            $table->string('extplayer', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('ref', 255)->nullable();
            $table->string('accName', 255)->nullable();
            $table->string('accNumber', 255)->nullable();
            $table->decimal('saldo', 15, 2)->default(0.00);
            $table->decimal('saldo_slot', 15, 2)->default(0.00);
            $table->decimal('saldo_game', 15, 2)->default(0.00);
            $table->string('level', 50)->default('New Player');
            $table->integer('reward')->default(0);
            $table->integer('point_player')->default(0);
            $table->integer('exp_player')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'role', 'phone', 'whatsapp', 'bank', 'informasi', 'pembayaran',
                'country', 'extplayer', 'last_seen_at', 'ref', 'accName', 'accNumber',
                'saldo', 'saldo_slot', 'saldo_game', 'level', 'reward', 'point_player', 'exp_player',
            ]);
        });
    }
};