<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livechat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 100);
            $table->string('email', 100)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });

        Schema::create('livechat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('sender_type', ['user', 'admin']);
            $table->text('message');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->foreign('session_id')->references('id')->on('livechat_sessions')->onDelete('cascade');
            $table->index('session_id');
            $table->index('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livechat_messages');
        Schema::dropIfExists('livechat_sessions');
    }
};
