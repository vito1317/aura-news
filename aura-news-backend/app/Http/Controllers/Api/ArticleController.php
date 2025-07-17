<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->where(function($q) {
                $q->where('image_url', 'like', 'http%');
            })
            ->whereNotNull('summary')
            ->whereNotNull('content')
            ->where('summary', '!=', '')
            ->where('content', '!=', '')
            ->whereRaw('CHAR_LENGTH(content) >= 500')
            ->latest()
            ->paginate(12);

        return response()->json($articles);
    }
    
    public function show(Article $article)
    {
        return response()->json($article);
    }

}