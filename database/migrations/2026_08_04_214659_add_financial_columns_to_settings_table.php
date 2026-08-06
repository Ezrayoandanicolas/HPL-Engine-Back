<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('min_deposit', 15, 2)->nullable()->after('web');
            $table->decimal('max_deposit', 15, 2)->nullable()->after('min_deposit');
            $table->decimal('fee_deposit', 5, 2)->nullable()->after('max_deposit');
            $table->decimal('min_withdraw', 15, 2)->nullable()->after('fee_deposit');
            $table->decimal('max_withdraw', 15, 2)->nullable()->after('min_withdraw');
            $table->decimal('fee_withdraw', 5, 2)->nullable()->after('max_withdraw');
            $table->string('telp')->nullable()->after('telegram');
            $table->string('running_text')->nullable()->after('telp');
            $table->string('theme')->nullable()->after('running_text');
            $table->string('bank_name')->nullable()->after('theme');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('bank_holder')->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['min_deposit', 'max_deposit', 'fee_deposit', 'min_withdraw', 'max_withdraw', 'fee_withdraw', 'telp', 'running_text', 'theme', 'bank_name', 'bank_account', 'bank_holder']);
        });
    }
};
