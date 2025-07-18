<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function meta($id)
    {
        $article = Article::findOrFail($id);
        return view('article-meta', compact('article'));
    }
} 