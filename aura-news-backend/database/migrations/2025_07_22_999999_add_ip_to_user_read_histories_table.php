<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_read_histories', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('article_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_read_histories', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
    }
}; 