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
            $table->text('verified_content')->nullable()->after('original_content')->comment('查證的內文內容');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_scan_results', function (Blueprint $table) {
            $table->dropColumn('verified_content');
        });
    }
};
