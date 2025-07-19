<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Jobs\AIScanFakeNewsJob;

class ArticleCredibilityController extends Controller
{
    /**
     * 獲取文章的可信度分析結果
     */
    public function getCredibility($articleId)
    {
        try {
            $article = Article::findOrFail($articleId);
            
            // 如果文章還沒有可信度分析，返回 null
            if (!$article->credibility_analysis) {
                return response()->json([
                    'has_analysis' => false,
                    'message' => '此文章尚未進行可信度分析'
                ]);
            }

            return response()->json([
                'has_analysis' => true,
                'credibility_score' => $article->credibility_score,
                'credibility_analysis' => $article->credibility_analysis,
                'credibility_checked_at' => $article->credibility_checked_at,
                'article_title' => $article->title,
                'article_source' => $article->source_url,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => '文章不存在或查詢失敗',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * 手動觸發文章可信度分析
     */
    public function triggerAnalysis($articleId)
    {
        try {
            $article = Article::findOrFail($articleId);
            
            // 檢查是否已經有分析結果且時間在 24 小時內
            if ($article->credibility_analysis && 
                $article->credibility_checked_at && 
                $article->credibility_checked_at->diffInHours(now()) < 24) {
                return response()->json([
                    'message' => '此文章在 24 小時內已進行過可信度分析',
                    'credibility_score' => $article->credibility_score,
                    'credibility_checked_at' => $article->credibility_checked_at,
                ]);
            }

            // 創建任務 ID
            $taskId = (string) Str::uuid();
            
            // 將文章內容存入快取
            $content = strip_tags($article->content);
            Cache::put("ai_scan_content_{$taskId}", $content, 600);
            
            // 更新進度狀態
            Cache::put("ai_scan_progress_{$taskId}", [
                'progress' => '已接收文章可信度分析請求',
                'result' => null,
            ], 600);

            // 分發 AI 掃描任務
            AIScanFakeNewsJob::dispatch($taskId, $content, request()->ip(), $article->id);

            return response()->json([
                'message' => '可信度分析已開始',
                'taskId' => $taskId,
                'article_id' => $article->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => '觸發可信度分析失敗',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取文章可信度分析進度
     */
    public function getAnalysisProgress($taskId)
    {
        $data = Cache::get("ai_scan_progress_{$taskId}");
        if (!$data) {
            return response()->json(['progress' => 'not_found'], 404);
        }

        // 如果分析完成，更新文章的可信度資訊
        if (isset($data['progress']) && $data['progress'] === '完成' && isset($data['result'])) {
            $this->updateArticleCredibility($taskId, $data['result']);
        }

        return response()->json($data);
    }

    /**
     * 更新文章的可信度資訊
     */
    private function updateArticleCredibility($taskId, $analysisResult)
    {
        try {
            // 從快取中獲取文章內容，用於匹配文章
            $content = Cache::get("ai_scan_content_{$taskId}");
            if (!$content) {
                return;
            }

            // 尋找對應的文章（通過內容片段匹配）
            $contentSnippet = mb_substr($content, 0, 200);
            $article = Article::whereRaw('SUBSTRING(REPLACE(content, "<[^>]*>", ""), 1, 200) LIKE ?', ['%' . $contentSnippet . '%'])
                             ->orWhere('content', 'LIKE', '%' . $contentSnippet . '%')
                             ->first();

            if ($article) {
                // 提取可信度分數
                $credibilityScore = $this->extractCredibilityScore($analysisResult);
                
                // 更新文章
                $article->update([
                    'credibility_analysis' => $analysisResult,
                    'credibility_score' => $credibilityScore,
                    'credibility_checked_at' => now(),
                ]);

                \Log::info('文章可信度分析完成並更新', [
                    'article_id' => $article->id,
                    'credibility_score' => $credibilityScore,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('更新文章可信度失敗', [
                'taskId' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 從 AI 回應中提取可信度百分比
     */
    private function extractCredibilityScore(string $aiText): ?int
    {
        if (preg_match('/【可信度：(\d+)%】/', $aiText, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}
