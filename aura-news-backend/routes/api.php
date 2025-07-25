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
use App\Http\Controllers\Api\ArticleCredibilityController;
use App\Http\Controllers\Api\NewsDataController;
use App\Http\Controllers\Api\ArticleRecommendController;
use App\Http\Controllers\Api\WebauthnController;
use Spatie\LaravelPasskeys\Models\Passkey;
use App\Models\User;

Route::post('/login', [AuthController::class, 'login']);
Route::post('register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::post('articles/{id}/comments', [\App\Http\Controllers\Api\ArticleController::class, 'addComment']);
    Route::delete('/comments/{commentId}', [\App\Http\Controllers\Api\ArticleController::class, 'deleteComment']);
    Route::delete('/comments', [\App\Http\Controllers\Api\ArticleController::class, 'deleteAllComments']);
});

// 讓所有人都能取得推薦
Route::get('/articles/recommend', [ArticleRecommendController::class, 'recommend']);
Route::post('/articles/{article}/read', [App\Http\Controllers\Api\ArticleRecommendController::class, 'markAsRead']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/stats', [ArticleController::class, 'stats']);
Route::get('/categories/{category}/articles', [ArticleSearchController::class, 'getByCategory']);
Route::get('/search', [ArticleSearchController::class, 'search']);
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

Route::get('/articles/{articleId}/credibility', [ArticleCredibilityController::class, 'getCredibility']);
Route::post('/articles/{articleId}/credibility/analyze', [ArticleCredibilityController::class, 'triggerAnalysis']);
Route::get('/articles/credibility/progress/{taskId}', [ArticleCredibilityController::class, 'getAnalysisProgress']);

Route::get('/articles/{article}', [ArticleController::class, 'show']);
Route::get('articles/{id}/comments', [\App\Http\Controllers\Api\ArticleController::class, 'comments']);
Route::post('articles/{id}/comments', [\App\Http\Controllers\Api\ArticleController::class, 'addComment']);
Route::delete('/comments/{commentId}', [\App\Http\Controllers\Api\ArticleController::class, 'deleteComment']);
Route::delete('/comments', [\App\Http\Controllers\Api\ArticleController::class, 'deleteAllComments']);

Route::get('/test-cors', function () {
    return response('CORS OK')->header('Access-Control-Allow-Origin', '*');
});

Route::post('/ai/scan-fake-news', [\App\Http\Controllers\Api\AIScanFakeNewsController::class, 'scan']);
Route::post('/ai/scan-fake-news/start', [\App\Http\Controllers\Api\AIScanFakeNewsController::class, 'start']);
Route::get('/ai/scan-fake-news/progress/{taskId}', [\App\Http\Controllers\Api\AIScanFakeNewsController::class, 'progress']);
Route::get('/ai/scan-fake-news/result/{taskId}', [\App\Http\Controllers\Api\AIScanFakeNewsController::class, 'getResult']);
Route::get('ai/scan-fake-news/usage-count', [\App\Http\Controllers\Api\AIScanFakeNewsController::class, 'usageCount']);

Route::get('/newsdata/search', [NewsDataController::class, 'search']);
Route::get('/newsdata/latest', [NewsDataController::class, 'latest']);
Route::get('/newsdata/categories', [NewsDataController::class, 'categories']);

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
    Route::post('/images/upload', [ImageUploadController::class, 'upload']);
    Route::apiResource('articles', AdminArticleController::class);
    Route::post('articles/ai-generate', [AdminArticleController::class, 'aiGenerate']);
    Route::apiResource('categories', CategoryController::class);
});

Route::get('auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/passkey/register/options', [\App\Http\Controllers\PasskeyRegisterController::class, 'options']);
Route::post('/passkey/register/verify', [\App\Http\Controllers\PasskeyRegisterController::class, 'verify']);
Route::post('/passkey/login/options', [\App\Http\Controllers\PasskeyLoginController::class, 'options']);
Route::post('/passkey/login/verify', [\App\Http\Controllers\PasskeyLoginController::class, 'verify']);

Route::get('/passkey/check', function (Request $request) {
    $email = $request->query('email');
    $user = User::where('email', $email)->first();
    if (!$user) {
        return response()->json(['exists' => false]);
    }
    $hasPasskey = Passkey::where('authenticatable_id', $user->id)->exists();
    return response()->json(['exists' => $hasPasskey]);
});