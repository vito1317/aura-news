<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleController as WebArticleController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/articles/{id}', [WebArticleController::class, 'meta'])->where('id', '[0-9]+');
