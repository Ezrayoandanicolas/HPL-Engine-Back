<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends \Illuminate\Database\Migrations\Migration
{
    public function up(): void
    {
        Schema::table('livechat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->after('rating');
            $table->boolean('is_offline')->default(false)->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('livechat_sessions', function (Blueprint $table) {
            $table->dropColumn(['assigned_to', 'is_offline']);
        });
    }
};
