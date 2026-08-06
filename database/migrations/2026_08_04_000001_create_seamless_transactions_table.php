<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seamless_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txn_id', 128)->unique();
            $table->string('round_id', 64)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_code');
            $table->string('game_type')->nullable();
            $table->string('provider_code')->nullable();
            $table->string('game_code')->nullable();
            $table->string('txn_type')->default('debit_credit');
            $table->decimal('bet_money', 18, 2)->default(0);
            $table->decimal('win_money', 18, 2)->default(0);
            $table->decimal('balance_before', 18, 2)->default(0);
            $table->decimal('balance_after', 18, 2)->default(0);
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seamless_transactions');
    }
};
