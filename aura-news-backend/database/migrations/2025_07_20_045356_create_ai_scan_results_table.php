<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scan_results', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique()->index();
            $table->text('original_content');
            $table->text('analysis_result');
            $table->integer('credibility_score')->nullable();
            $table->string('client_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('search_keywords')->nullable();
            $table->json('verification_sources')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['created_at']);
            $table->index(['credibility_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scan_results');
    }
};
