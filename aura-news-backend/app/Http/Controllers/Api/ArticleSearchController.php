<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use App\Models\Category;

class ArticleSearchController extends Controller
{
    public function getByCategory(Category $category)
    {
        $articles = $category->articles()
                             ->whereNotNull('image_url')
                             ->whereNotNull('summary')
                             ->latest()
                             ->paginate(12);

        return response()->json([
            'category' => $category,
            'articles' => $articles,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->validate(['q' => 'required|string|min:2'])['q'];

        $articles = Article::whereNotNull('image_url')
                           ->whereNotNull('summary')
                           ->where(function ($qB) use ($query) {
                               $qB->where('title', 'like', "%{$query}%")
                                  ->orWhere('content', 'like', "%{$query}%")
                                  ->orWhere('keywords', 'like', "%{$query}%");
                           })
                           ->latest()
                           ->paginate(12);
        
        return response()->json([
            'query' => $query,
            'articles' => $articles,
        ]);
    }
}