<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class UpdatePopularArticles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $articleId;

    public function __construct($articleId = null)
    {
        $this->articleId = $articleId;
    }

    public function handle()
    {
        if ($this->articleId) {
            // 更新單篇文章
            $this->updateSingleArticle();
        } else {
            // 更新所有文章
            $this->updateAllArticles();
        }
    }

    private function updateSingleArticle()
    {
        $article = Article::find($this->articleId);
        if (!$article) {
            \Log::warning("文章 ID {$this->articleId} 不存在");
            return;
        }

        $popularityScore = $this->calculatePopularityScore($article);
        $article->update(['popularity_score' => $popularityScore]);
        \Log::info("單篇文章更新 - ID: {$article->id}, 熱門度分數: {$popularityScore}");
    }

    private function updateAllArticles()
    {
        \Log::info('開始更新所有文章熱門度分數');
        
        $articles = Article::whereNotNull('content')
            ->where('content', '!=', '')
            ->whereRaw('CHAR_LENGTH(content) >= 500')
            ->get();
        
        foreach ($articles as $article) {
            // 若 keywords 為空，自動補齊
            if (empty($article->keywords)) {
                try {
                    $gemini = resolve(\Gemini\Client::class);
                    $prompt = "請根據以下新聞內容，產生3~5個適合用於分類與推薦的繁體中文關鍵字或短語，僅回傳關鍵字本身，用逗號分隔：\n\n" . strip_tags($article->content);
                    $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
                    $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $result->text()));
                    $article->keywords = $keywords;
                    $article->save();
                    \Log::info('已補齊關鍵字: ' . $article->id . ' => ' . $keywords);
                } catch (\Exception $e) {
                    \Log::error('補齊關鍵字失敗: ' . $article->id . ' - ' . $e->getMessage());
                }
            }
            $popularityScore = $this->calculatePopularityScore($article);
            
            // 更新文章熱門度分數
            $article->update(['popularity_score' => $popularityScore]);
            \Log::info("文章 ID: {$article->id}, 熱門度分數: {$popularityScore}");
        }
        
        \Log::info('所有文章熱門度分數更新完成');
    }
    
    private function calculatePopularityScore($article)
    {
        $score = 0;
        
        // 可信度分數 (25% 權重)
        if ($article->credibility_score) {
            $score += $article->credibility_score * 0.25;
        }
        
        // 觀看次數分數 (50% 權重)
        if ($article->view_count) {
            $viewScore = min(100, $article->view_count / 10);
            $score += $viewScore * 0.50;
        }
        
        // 發布時間分數 (15% 權重)
        if ($article->published_at) {
            $publishedDate = $article->published_at;
            $now = now();
            $daysDiff = $now->diffInDays($publishedDate);
            $timeScore = max(0, 100 - $daysDiff * 2);
            $score += $timeScore * 0.15;
        }
        
        // 內容長度分數 (10% 權重)
        if ($article->content) {
            $contentLength = strlen($article->content);
            $lengthScore = min(100, $contentLength / 10);
            $score += $lengthScore * 0.1;
        }
        
        // 標題長度分數 (10% 權重)
        if ($article->title) {
            $titleLength = strlen($article->title);
            if ($titleLength >= 10 && $titleLength <= 50) {
                $titleScore = 100;
            } elseif ($titleLength < 10) {
                $titleScore = $titleLength * 10;
            } else {
                $titleScore = max(0, 100 - ($titleLength - 50));
            }
            $score += $titleScore * 0.1;
        }
        
        // 基礎分數 (15% 權重)
        //$score += 15;
        
        return round($score, 2);
    }
}
