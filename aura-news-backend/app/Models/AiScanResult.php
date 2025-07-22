<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiScanResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'original_content',
        'verified_content',
        'analysis_result',
        'credibility_score',
        'client_ip',
        'user_agent',
        'search_keywords',
        'verification_sources',
        'completed_at',
        'external_summaries',
    ];
    
    protected $casts = [
        'search_keywords' => 'array',
        'verification_sources' => 'array',
        'completed_at' => 'datetime',
        'external_summaries' => 'array',
    ];

    public function extractCredibilityScore(): ?int
    {
        if (preg_match('/【可信度：(\d+)%】/', $this->analysis_result, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function extractVerificationSources(): array
    {
        if (preg_match('/【查證出處】([\s\S]*)$/', $this->analysis_result, $matches)) {
            $sources = trim($matches[1]);
            return array_filter(array_map('trim', explode("\n", $sources)));
        }
        return [];
    }

    public function getAnalysisContent(): string
    {
        $content = $this->analysis_result;
        $content = preg_replace('/【可信度：.+?】\s*/', '', $content);
        $content = preg_replace('/【查證出處】([\s\S]*)$/', '', $content);
        return trim($content);
    }
}
