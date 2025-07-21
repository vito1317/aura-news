<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use App\Models\Comment;

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
            'data' => $articles->items(),
            'total' => $total,
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
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

    // 新增：取得指定文章留言
    public function comments($id)
    {
        $comments = \App\Models\Comment::where('article_id', $id)
            ->with('user:id,nickname')
            ->orderBy('created_at', 'desc')
            ->get();
        // 回傳時 user_name 以 user.nickname 為主
        $comments->transform(function ($c) {
            $c->display_name = $c->user ? $c->user->nickname : $c->user_name;
            unset($c->user); // 前端只用 display_name
            return $c;
        });
        return response()->json($comments);
    }

    // 新增：發表留言
    public function addComment(Request $request, $id)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);
        $user = $request->user();
        $comment = \App\Models\Comment::create([
            'article_id' => $id,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->nickname : '匿名',
            'content' => $validated['content'],
        ]);
        return response()->json($comment, 201);
    }

    // 刪除留言
    public function deleteComment(Request $request, $commentId)
    {
        $user = $request->user();
        $comment = \App\Models\Comment::findOrFail($commentId);
        if (!$user || ($comment->user_id !== $user->id && $user->role !== 'admin')) {
            return response()->json(['message' => '無權限刪除'], 403);
        }
        $comment->delete();
        return response()->json(['message' => '留言已刪除']);
    }

    // 管理員刪除所有留言
    public function deleteAllComments(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => '無權限刪除全部留言'], 403);
        }
        \App\Models\Comment::truncate();
        return response()->json(['message' => '所有留言已刪除']);
    }
}