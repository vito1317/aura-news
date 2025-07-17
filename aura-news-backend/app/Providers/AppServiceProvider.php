<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Article;
use App\Observers\ArticleObserver;

use Gemini\Client;
use Gemini\Enums\Region;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $apiKey = config('gemini.api_key');
            
            return \Gemini::client($apiKey);
        });

        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Dusk\DuskServiceProvider::class);
        }
    }

    public function boot(): void
    {
        Article::observe(ArticleObserver::class);
        // 強制所有 route 與分頁產生 https 連結
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}