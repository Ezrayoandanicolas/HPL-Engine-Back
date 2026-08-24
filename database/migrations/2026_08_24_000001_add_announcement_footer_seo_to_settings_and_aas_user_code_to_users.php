<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'announcement_text')) {
                $table->text('announcement_text')->nullable()->after('running_text');
            }
            if (!Schema::hasColumn('settings', 'footer_seo')) {
                $table->text('footer_seo')->nullable()->after('seo');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'aas_user_code')) {
                $table->string('aas_user_code', 50)->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['announcement_text', 'footer_seo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('aas_user_code');
        });
    }
};
