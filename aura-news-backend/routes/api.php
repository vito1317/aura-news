<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\ImageUploadController;
use App\Http\Controllers\Api\Admin\CategoryController;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\ArticleSearchController;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{article}', [ArticleController::class, 'show']);
Route::get('/categories/{category}/articles', [ArticleSearchController::class, 'getByCategory']);
Route::get('/search', [ArticleSearchController::class, 'search']);
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

Route::get('/test-cors', function () {
    return response('CORS OK')->header('Access-Control-Allow-Origin', '*');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
    Route::post('/images/upload', [ImageUploadController::class, 'upload']);
    Route::apiResource('articles', AdminArticleController::class);
    Route::post('articles/ai-generate', [AdminArticleController::class, 'aiGenerate']);
    Route::apiResource('categories', CategoryController::class);
});