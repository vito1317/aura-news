<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 12);
        $sortBy = $request->get('sort_by', 'popularity');
        
        $total = Article::whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->where(function($q) {
                $q->where('image_url', 'like', 'http%');
            })
            ->whereNotNull('summary')
            ->whereNotNull('content')
            ->where('summary', '!=', '')
            ->where('content', '!=', '')
            ->whereRaw('CHAR_LENGTH(content) >= 500')
            ->count();

        $query = Article::whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->where(function($q) {
                $q->where('image_url', 'like', 'http%');
            })
            ->whereNotNull('summary')
            ->whereNotNull('content')
            ->where('summary', '!=', '')
            ->where('content', '!=', '')
            ->whereRaw('CHAR_LENGTH(content) >= 500');

        if ($sortBy === 'latest') {
            $query->orderBy('published_at', 'desc');
        } else {
            $query->orderBy('popularity_score', 'desc')
                  ->orderBy('published_at', 'desc');
        }

        $articles = $query->paginate($perPage);

        return response()->json([
            'data' => $articles->toArray(),
            'total' => $total,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $perPage,
            'message' => 'Articles retrieved successfully'
        ]);
    }
    
    public function show(Article $article)
    {
        $article->increment('view_count');
        
        return response()->json($article);
    }
    
    public function stats()
    {
        $totalViews = Article::sum('view_count');
        $totalArticles = Article::whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->where(function($q) {
                $q->where('image_url', 'like', 'http%');
            })
            ->whereNotNull('summary')
            ->whereNotNull('content')
            ->where('summary', '!=', '')
            ->where('content', '!=', '')
            ->whereRaw('CHAR_LENGTH(content) >= 500')
            ->count();
        
        $avgCredibility = Article::whereNotNull('credibility_score')
            ->where('credibility_score', '>', 0)
            ->avg('credibility_score');
        
        return response()->json([
            'total_views' => $totalViews,
            'total_articles' => $totalArticles,
            'avg_credibility' => round($avgCredibility, 1),
            'message' => 'Stats retrieved successfully'
        ]);
    }

}