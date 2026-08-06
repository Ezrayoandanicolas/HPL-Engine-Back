<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();

            $table->string('game_uid')->unique();
            $table->string('provider_code')->nullable();
            $table->string('provider_name')->nullable();

            $table->string('game_name');
            $table->string('game_type')->nullable();

            $table->string('image_url')->nullable();

            $table->decimal('rtp', 5, 2)->nullable();
            $table->string('volatility')->nullable();

            $table->decimal('min_bet', 15, 2)->nullable();
            $table->decimal('max_bet', 15, 2)->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sports');
    }
};