<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_read_histories', function (Blueprint $table) {
            $table->dropUnique('user_read_histories_user_id_article_id_unique');
            $table->unique(['user_id', 'article_id']);
            $table->unique(['session_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_read_histories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'article_id']);
            $table->dropUnique(['session_id', 'article_id']);
            $table->unique(['user_id', 'article_id']); // Restore original if needed
        });
    }
}; 