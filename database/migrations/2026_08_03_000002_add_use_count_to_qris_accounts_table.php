<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qris_accounts', function (Blueprint $table) {
            $table->unsignedInteger('use_count')->default(0)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('qris_accounts', function (Blueprint $table) {
            $table->dropColumn('use_count');
        });
    }
};
