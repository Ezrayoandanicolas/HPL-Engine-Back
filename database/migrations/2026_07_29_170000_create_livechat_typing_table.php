<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livechat_typing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('sender_type', ['user', 'admin']);
            $table->text('text')->nullable();
            $table->timestamp('updated_at');
            $table->foreign('session_id')->references('id')->on('livechat_sessions')->onDelete('cascade');
            $table->unique(['session_id', 'sender_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livechat_typing');
    }
};
