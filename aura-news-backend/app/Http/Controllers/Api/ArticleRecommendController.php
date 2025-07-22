<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserReadHistory;
use App\Models\Article;
use Illuminate\Support\Facades\Auth;

class ArticleRecommendController extends Controller
{
    // 上報閱讀紀錄
    public function markAsRead(Request $request, $articleId)
    {
        $clientIp = $request->header('X-Forwarded-For');
        $sessionId = $request->session()->getId();
        \Log::info('markAsRead debug', [
            'ip' => $clientIp,
            'session_id' => $sessionId,
            'x-forwarded-for' => $request->header('x-forwarded-for'),
            'user' => $request->user(),
            'all_headers' => $request->headers->all(),
        ]);
        // Use sanctum guard to get user if Bearer token is present
        $user = Auth::guard('sanctum')->user() ?? $request->user();
        $ip = $clientIp;
        if ($user) {
            UserReadHistory::updateOrCreate(
                ['user_id' => $user->id, 'article_id' => $articleId],
                ['read_at' => now(), 'ip' => $ip, 'session_id' => $sessionId]
            );
        } else {
            UserReadHistory::updateOrCreate(
                ['session_id' => $sessionId, 'article_id' => $articleId],
                ['read_at' => now(), 'ip' => $ip]
            );
        }
        return response()->json(['success' => true]);
    }

    // 根據已讀文章的 keywords 推薦
    public function recommend(Request $request)
    {
        $user = $request->user();
        $ip = $request->header('X-Forwarded-For');
        $sessionId = $request->session()->getId();
        if ($user) {
            $readQuery = UserReadHistory::where('user_id', $user->id);
        } else {
            // 優先用 session_id，再用 ip
            $readQuery = UserReadHistory::whereNull('user_id')
                ->where(function($q) use ($sessionId, $ip) {
                    $q->where('session_id', $sessionId);
                    if ($ip) {
                        $q->orWhere('ip', $ip);
                    }
                });
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