<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserReadHistory;
use App\Models\Article;

class ArticleRecommendController extends Controller
{
    // 上報閱讀紀錄
    public function markAsRead(Request $request, $articleId)
    {
        $user = $request->user();
        $ip = $request->ip();
        if ($user) {
            UserReadHistory::updateOrCreate(
                ['user_id' => $user->id, 'article_id' => $articleId],
                ['read_at' => now(), 'ip' => $ip]
            );
        } else {
            UserReadHistory::updateOrCreate(
                ['user_id' => 0, 'article_id' => $articleId, 'ip' => $ip],
                ['read_at' => now()]
            );
        }
        return response()->json(['success' => true]);
    }

    // 根據已讀文章的 keywords 推薦
    public function recommend(Request $request)
    {
        $user = $request->user();
        $ip = $request->ip();
        if ($user) {
            $readQuery = UserReadHistory::where('user_id', $user->id);
        } else {
            $readQuery = UserReadHistory::where('user_id', 0)->where('ip', $ip);
        }
        $readArticleIds = $readQuery->pluck('article_id');

        // 取得已讀文章的所有關鍵字
        $keywords = Article::whereIn('id', $readArticleIds)
            ->pluck('keywords')
            ->flatMap(function ($item) {
                return array_filter(array_map('trim', explode(',', $item)));
            })
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        // 推薦擁有相同/相似關鍵字的其他文章
        $query = Article::query()->whereNotIn('id', $readArticleIds);
        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('keywords', 'like', "%$kw%");
                }
            });
        }
        $recommendArticles = $query->orderByDesc('popularity_score')->limit(10)->get();

        // 若沒資料，推薦熱門
        if ($recommendArticles->isEmpty()) {
            $recommendArticles = Article::orderByDesc('popularity_score')->limit(10)->get();
        }

        return response()->json($recommendArticles);
    }
} 