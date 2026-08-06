<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiver_games', function (Blueprint $table) {
            $table->id();
            $table->string('game_code')->nullable();
            $table->string('game_name')->nullable();
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->string('game_provider')->nullable();
            $table->string('game_category', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiver_games');
    }
};