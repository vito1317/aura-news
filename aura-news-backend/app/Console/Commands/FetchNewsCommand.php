<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Article;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\NewsDataApiService;

class FetchNewsCommand extends Command
{
    protected $signature = 'app:fetch-news {category=科技} {--api=newsapi} {--language=zh} {--size=20}';

    protected $description = '從 NewsAPI.org 或 NewsData.io 根據指定的分類抓取新聞和文章';

    protected $newsDataService;

    public function __construct(NewsDataApiService $newsDataService)
    {
        parent::__construct();
        $this->newsDataService = $newsDataService;
    }

    public function handle()
    {
        $categoryName = $this->argument('category');
        $apiType = $this->option('api');
        $language = $this->option('language');
        $size = $this->option('size');

        $this->info("開始從 {$apiType} 抓取 [{$categoryName}] 分類的新聞...");
        if ($apiType === 'newsdata') {
            $this->info("語言: {$language}, 數量: 10 (NewsData API 預設值)");
        } else {
            $this->info("語言: {$language}, 數量: {$size}");
        }

        if ($apiType === 'newsdata') {
            return $this->fetchFromNewsData($categoryName, $language, $size);
        } else {
            return $this->fetchFromNewsApi($categoryName, $language, $size);
        }
    }

    protected function fetchFromNewsApi($categoryName, $language, $size)
    {
        $apiKey = config('services.newsapi.key');
        if (!$apiKey) {
            $this->error('NewsAPI Key 未設定，請檢查 .env 檔案。');
            return 1;
        }
        
        $category = Category::firstOrCreate(
            ['name' => $categoryName], 
            ['slug' => Str::slug($categoryName)]
        );

        $response = Http::get('https://newsapi.org/v2/everything', [
            'q' => $categoryName,
            'language' => $language,
            'sortBy' => 'publishedAt',
            'apiKey' => $apiKey,
            'pageSize' => $size,
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
                // 過濾無效圖片 URL
                $invalidPatterns = [
                    '/consent\.yahoo\.com/',
                    '/placeholder\.com/',
                    '/default\.jpg/',
                    '/no-image/',
                    '/blank\./',
                ];
                
                $isValidImage = true;
                foreach ($invalidPatterns as $pattern) {
                    if (preg_match($pattern, $imageUrl)) {
                        $isValidImage = false;
                        break;
                    }
                }
                
                if (!$isValidImage) {
                    continue;
                }
                
                try {
                    $client = new \GuzzleHttp\Client(['timeout' => 5, 'verify' => false]);
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
                'published_at' => Carbon::parse($fetchedArticle['publishedAt'])->addHours(8)->setTimezone('Asia/Taipei'),
            ]);
            dispatch(new \App\Jobs\ProcessArticleData($article));
            
            // 觸發熱門度分析
            dispatch(new \App\Jobs\UpdatePopularArticles($article->id));
            $count++;
        }

        $this->info("抓取完成！共為 [{$categoryName}] 分類處理了 {$count} 篇新文章。");
        return 0;
    }

    protected function fetchFromNewsData($categoryName, $language, $size)
    {
        $category = Category::firstOrCreate(
            ['name' => $categoryName], 
            ['slug' => Str::slug($categoryName)]
        );

        $articles = $this->newsDataService->searchArticles($categoryName, $language, $size);

        if (!$articles) {
            $this->error('無法從 NewsData API 獲取資料');
            return 1;
        }

        $count = 0;
        $skipped = 0;

        foreach ($articles as $articleData) {
            // 檢查是否已存在
            if (Article::where('source_url', $articleData['source_url'])->exists()) {
                $skipped++;
                continue;
            }

            $this->line("正在建立: " . $articleData['title']);
            
            try {
                $article = Article::create([
                    'source_url' => $articleData['source_url'],
                    'title' => $articleData['title'],
                    'content' => $articleData['content'],
                    'image_url' => $articleData['image_url'],
                    'summary' => $articleData['summary'],
                    'category_id' => $category->id,
                    'author' => $articleData['author'],
                    'status' => 1,
                    'published_at' => Carbon::parse($articleData['published_at'])->addHours(8)->setTimezone('Asia/Taipei'),
                ]);

                // 觸發文章處理任務
                dispatch(new \App\Jobs\ProcessArticleData($article));
                
                // 觸發熱門度分析
                dispatch(new \App\Jobs\UpdatePopularArticles($article->id));
                $count++;
            } catch (\Exception $e) {
                $this->error("建立文章失敗: " . $e->getMessage());
                continue;
            }
        }

        $this->info("抓取完成！");
        $this->info("新增文章: {$count} 篇");
        $this->info("跳過重複: {$skipped} 篇");
        $this->info("總計處理: " . ($count + $skipped) . " 篇");

        return 0;
    }
}