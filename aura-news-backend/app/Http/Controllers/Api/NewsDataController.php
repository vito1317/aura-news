<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\NewsDataApiService;
use Illuminate\Support\Facades\Cache;

class NewsDataController extends Controller
{
    protected $newsDataService;

    public function __construct(NewsDataApiService $newsDataService)
    {
        $this->newsDataService = $newsDataService;
    }

    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'language' => 'string|in:chinese,english',
            'size' => 'integer|min:1|max:50',
        ]);

        $query = $request->input('q');
        $language = $request->input('language', 'chinese');
        $size = $request->input('size', 20);

        // 使用快取來避免重複請求
        $cacheKey = "newsdata_search_{$query}_{$language}_{$size}";
        
        $articles = Cache::remember($cacheKey, 300, function () use ($query, $language, $size) {
            return $this->newsDataService->searchArticles($query, $language, $size);
        });

        if (!$articles) {
            return response()->json([
                'success' => false,
                'message' => '無法從 NewsData API 獲取資料',
                'data' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '搜尋成功',
            'data' => [
                'query' => $query,
                'language' => $language,
                'total' => $articles->count(),
                'articles' => $articles->take($size)->values()
            ]
        ]);
    }

    public function latest(Request $request)
    {
        $request->validate([
            'category' => 'string|max:50',
            'language' => 'string|in:chinese,english',
            'size' => 'integer|min:1|max:50',
        ]);

        $category = $request->input('category', '科技');
        $language = $request->input('language', 'chinese');
        $size = $request->input('size', 20);

        // 使用快取來避免重複請求
        $cacheKey = "newsdata_latest_{$category}_{$language}_{$size}";
        
        $articles = Cache::remember($cacheKey, 300, function () use ($category, $language, $size) {
            return $this->newsDataService->searchArticles($category, $language, $size);
        });

        if (!$articles) {
            return response()->json([
                'success' => false,
                'message' => '無法從 NewsData API 獲取最新資料',
                'data' => []
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => '獲取最新資料成功',
            'data' => [
                'category' => $category,
                'language' => $language,
                'total' => $articles->count(),
                'articles' => $articles->take($size)->values()
            ]
        ]);
    }

    public function categories()
    {
        $categories = [
            '科技' => 'technology',
            '政治' => 'politics', 
            '商業' => 'business',
            '體育' => 'sports',
            '娛樂' => 'entertainment',
            '健康' => 'health',
            '科學' => 'science',
            '頭條' => 'top',
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
} 