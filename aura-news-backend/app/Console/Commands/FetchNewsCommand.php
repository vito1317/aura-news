<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Article;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FetchNewsCommand extends Command
{
    protected $signature = 'app:fetch-news {category=科技}';

    protected $description = '從 NewsAPI.org 根據指定的分類抓取新聞';

    public function handle()
    {
        $categoryName = $this->argument('category');
        $this->info("開始從 NewsAPI.org 抓取 [{$categoryName}] 分類的新聞...");
        
        $apiKey = env('NEWS_API_KEY');
        if (!$apiKey) {
            $this->error('NewsAPI Key 未設定，請檢查 .env 檔案。');
            return 1;
        }
        
        $category = Category::firstOrCreate(
            ['name' => $categoryName], 
            ['slug' => $categoryName]
        );

        $response = Http::get('https://newsapi.org/v2/everything', [
            'q' => $categoryName,
            'language' => 'zh',
            'sortBy' => 'publishedAt',
            'apiKey' => $apiKey,
            'pageSize' => 20,
        ]);

        if ($response->failed()) {
            $this->error("從 NewsAPI 獲取 [{$categoryName}] 資料失敗: " . $response->reason());
            return 1;
        }

        $fetchedArticles = $response->json()['articles'];
        $count = 0;

        foreach ($fetchedArticles as $fetchedArticle) {
            if (empty($fetchedArticle['url']) || empty($fetchedArticle['title'])) {
                continue;
            }
            // Yahoo consent 頁面過濾
            if (strpos($fetchedArticle['url'], 'consent.yahoo.com/v2/collectConsent?') !== false) {
                continue;
            }
            if (Article::where('source_url', $fetchedArticle['url'])->exists()) {
                continue;
            }
            
            // 檢查圖片有效性
            $imageUrl = $fetchedArticle['urlToImage'] ?? null;
            if ($imageUrl && preg_match('/^https?:\/\//', $imageUrl)) {
                try {
                    $client = new \GuzzleHttp\Client(['timeout' => 3, 'verify' => false]);
                    $res = $client->head($imageUrl);
                    if ($res->getStatusCode() !== 200) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            } else {
                continue;
            }

            $this->line("正在建立: " . $fetchedArticle['title']);
            
            $article = Article::create([
                'source_url' => $fetchedArticle['url'],
                'title' => $fetchedArticle['title'],
                'content' => $fetchedArticle['content'] ?? $fetchedArticle['description'],
                'image_url' => $fetchedArticle['urlToImage'],
                'summary' => $fetchedArticle['description'] ?? null,
                'category_id' => $category->id,
                'author' => $fetchedArticle['source']['name'] ?? '未知來源',
                'status' => 1,
                'published_at' => Carbon::parse($fetchedArticle['publishedAt'])->setTimezone('Asia/Taipei'),
            ]);
            dispatch(new \App\Jobs\ProcessArticleData($article));
            $count++;
        }

        $this->info("抓取完成！共為 [{$categoryName}] 分類處理了 {$count} 篇新文章。");
        return 0;
    }
}