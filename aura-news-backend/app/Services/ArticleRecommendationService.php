<?php
namespace App\Services;

use App\Models\Article;
use App\Models\UserReadHistory;

class ArticleRecommendationService
{
    /**
     * 進階推薦演算法：
     * - 依據已讀關鍵字、分類、可信度、熱門度、AI 分析等多特徵混合推薦
     * - 權重可依需求調整
     */
    public function recommendForUserOrSession($userId = null, $sessionId = null, $ip = null, $limit = 10)
    {
        // 1. 取得已讀文章
        $readQuery = UserReadHistory::query();
        if ($userId) {
            $readQuery->where('user_id', $userId);
        } else {
            $readQuery->whereNull('user_id');
            if ($sessionId) {
                $readQuery->where('session_id', $sessionId);
            } elseif ($ip) {
                $readQuery->where('ip', $ip);
            }
        }
        $readArticleIds = $readQuery->pluck('article_id');

        // 2. 取得已讀文章的特徵
        $readArticles = Article::whereIn('id', $readArticleIds)->get();
        $keywords = $readArticles->pluck('keywords')
            ->flatMap(function ($item) {
                return array_filter(array_map('trim', explode(',', $item)));
            })
            ->unique()->filter()->values()->toArray();
        $categories = $readArticles->pluck('category_id')->unique()->filter()->values()->toArray();
        $minCredibility = $readArticles->min('credibility_score');
        $maxCredibility = $readArticles->max('credibility_score');

        // 3. 推薦未讀文章，依多特徵排序
        $query = Article::query()->whereNotIn('id', $readArticleIds);
        $query->when(!empty($keywords), function($q) use ($keywords) {
            $q->where(function($sub) use ($keywords) {
                foreach ($keywords as $kw) {
                    $sub->orWhere('keywords', 'like', "%$kw%");
                }
            });
        });
        $query->when(!empty($categories), function($q) use ($categories) {
            $q->orWhereIn('category_id', $categories);
        });
        // 可信度範圍
        if ($minCredibility && $maxCredibility) {
            $query->orWhereBetween('credibility_score', [$minCredibility, $maxCredibility]);
        }
        // 熱門度排序
        $query->orderByDesc('popularity_score');
        // 可信度排序
        $query->orderByDesc('credibility_score');
        // 其他可加：AI 分析、summary、作者偏好等
        return $query->limit($limit)->get();
    }
} 