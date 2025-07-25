<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->longText('credibility_analysis')->nullable()->after('summary');
            $table->integer('credibility_score')->nullable()->after('credibility_analysis');
            $table->timestamp('credibility_checked_at')->nullable()->after('credibility_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['credibility_analysis', 'credibility_score', 'credibility_checked_at']);
        });
    }
};
