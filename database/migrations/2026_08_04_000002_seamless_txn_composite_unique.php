<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seamless_transactions', function (Blueprint $table) {
            $table->dropUnique(['txn_id']);
            $table->unique(['txn_id', 'txn_type']);
        });
    }

    public function down(): void
    {
        Schema::table('seamless_transactions', function (Blueprint $table) {
            $table->dropUnique(['txn_id', 'txn_type']);
            $table->unique('txn_id');
        });
    }
};
