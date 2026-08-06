<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->string('attachment')->nullable()->after('message');
            $table->string('attachment_type')->nullable()->after('attachment');
        });
    }

    public function down(): void
    {
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment', 'attachment_type']);
        });
    }
};
