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
        Schema::table('ai_scan_results', function (Blueprint $table) {
            $table->json('external_summaries')->nullable()->after('verification_sources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_scan_results', function (Blueprint $table) {
            $table->dropColumn('external_summaries');
        });
    }
};
