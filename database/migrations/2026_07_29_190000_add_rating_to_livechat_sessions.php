<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        Schema::table('livechat_sessions', function (Blueprint $table) {
            $table->tinyInteger('rating')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('livechat_sessions', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
