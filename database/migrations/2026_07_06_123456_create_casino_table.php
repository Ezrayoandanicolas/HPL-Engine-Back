<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casino', function (Blueprint $table) {

            $table->id();

            $table->string('game_uid')->unique();
            $table->string('provider_code');
            $table->string('provider_name');
            $table->string('game_name');
            $table->string('game_type');

            $table->text('image_url')->nullable();

            $table->decimal('rtp', 5, 2)->default(0);
            $table->string('volatility')->nullable();

            $table->decimal('min_bet', 15, 2)->default(0);
            $table->decimal('max_bet', 15, 2)->default(0);

            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casino');
    }
};