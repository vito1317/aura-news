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

            // 特殊處理：UDN 新聞網
            $content = $fetchedArticle['content'] ?? $fetchedArticle['description'];
            if (strpos($fetchedArticle['url'], 'udn.com/news') !== false) {
                $udnUrl = $fetchedArticle['url'];
                if (preg_match('#^https://udn.com/news/story/(\d+)/(\d+)$#', $udnUrl, $matches)) {
                    $udnUrl = "https://udn.com/news/amp/story/{$matches[1]}/{$matches[2]}/";
                    \Log::info('FetchNewsCommand: 自動轉換 UDN AMP 版網址', ['amp_url' => $udnUrl]);
                }
                try {
                    $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
                    $res = $client->get($udnUrl);
                    if ($res->getStatusCode() === 200) {
                        $html = (string) $res->getBody();
                        $doc = new \DOMDocument();
                        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                        $xpath = new \DOMXPath($doc);
                        // UDN AMP 版主文擷取
                        if (strpos($udnUrl, '/news/amp/story/') !== false) {
                            $mainNode = $xpath->query('//main[contains(@class, "main")]')->item(0);
                            $udnText = [];
                            if ($mainNode) {
                                foreach ($mainNode->getElementsByTagName('p') as $p) {
                                    $udnText[] = trim($doc->saveHTML($p));
                                }
                            }
                            $joined = implode("\n\n", array_filter($udnText));
                            \Log::info('UDN AMP main <p> joined', ['joined' => mb_substr($joined,0,200), 'length' => mb_strlen(strip_tags($joined))]);
                            if (mb_strlen(strip_tags($joined)) > 10) {
                                $content = $joined;
                                \Log::info('UDN AMP 內容擷取成功，長度: ' . mb_strlen($joined));
                            }
                        } else {
                            // 支援多個主內容 XPath
                            $mainNode = null;
                            $possibleXPaths = [
                                '//div[contains(@class, "article-content__editor")]',
                                '//div[contains(@class, "article-content__paragraph")]',
                                '//section[contains(@class, "article-content__paragraph")]',
                                '//section',
                            ];
                            foreach ($possibleXPaths as $xpathStr) {
                                $mainNode = $xpath->query($xpathStr)->item(0);
                                if ($mainNode) {
                                    \Log::info('UDN 命中 XPath', ['xpath' => $xpathStr]);
                                    break;
                                }
                            }
                            if (!$mainNode) {
                                \Log::warning('UDN 找不到主內容區塊');
                            }
                            $udnText = [];
                            if ($mainNode) {
                                foreach ($mainNode->getElementsByTagName('p') as $p) {
                                    $udnText[] = trim($doc->saveHTML($p));
                                }
                            }
                            $joined = implode("\n\n", array_filter($udnText));
                            if (mb_strlen(strip_tags($joined)) > 50) {
                                $content = $joined;
                                \Log::info('UDN 內容擷取成功，長度: ' . mb_strlen($joined));
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // 忽略失敗，使用原本內容
                }
            }

            // 特殊處理：LINE TODAY 新聞
            if (strpos($fetchedArticle['url'], 'today.line.me/tw/v2/article') !== false) {
                try {
                    $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
                    $res = $client->get($fetchedArticle['url']);
                    if ($res->getStatusCode() === 200) {
                        $html = (string) $res->getBody();
                        $doc = new \DOMDocument();
                        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                        $xpath = new \DOMXPath($doc);
                        $mainNode = $xpath->query('//article[contains(@class, "news-content")]')->item(0);
                        $lineText = [];
                        if ($mainNode) {
                            foreach ($mainNode->getElementsByTagName('p') as $p) {
                                $lineText[] = trim($doc->saveHTML($p));
                            }
                        }
                        $joined = implode("\n\n", array_filter($lineText));
                        \Log::info('LINE TODAY main <p> joined', ['joined' => mb_substr($joined,0,200), 'length' => mb_strlen(strip_tags($joined))]);
                        if (mb_strlen(strip_tags($joined)) > 10) {
                            $content = $joined;
                            \Log::info('LINE TODAY 內容擷取成功，長度: ' . mb_strlen($joined));
                        }
                    }
                } catch (\Exception $e) {
                    // 忽略失敗，使用原本內容
                }
            }

            // 在主文最後加上原文出處
            if (!empty($content) && !empty($fetchedArticle['url'])) {
                $content .= "\n\n---\n原文出處：<a href='" . $fetchedArticle['url'] . "' target='_blank' rel='noopener noreferrer'>" . $fetchedArticle['url'] . "</a>";
            }

            $this->line("正在建立: " . $fetchedArticle['title']);
            
            // 在建立 Article 時，呼叫 AI 產生關鍵字
            $keywords = null;
            try {
                $gemini = resolve(\Gemini\Client::class);
                $prompt = "請根據以下新聞內容，產生3~5個適合用於分類與推薦的繁體中文關鍵字或短語，僅回傳關鍵字本身，用逗號分隔：\n\n" . strip_tags($content);
                $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
                $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $result->text()));
            } catch (\Exception $e) {
                $keywords = null;
            }

            // 新增：過濾主文少於500字的文章
            if (mb_strlen(strip_tags($content)) < 500) {
                $this->warn('主文少於500字，略過: ' . $fetchedArticle['title']);
                continue;
            }

            // 在建立 Article 前，檢查標題與內文相關性
            $isRelated = true;
            try {
                $gemini = resolve(\Gemini\Client::class);
                $prompt = "請判斷以下新聞標題與內文是否相關，僅回傳「相關」或「不相關」：\n\n標題：{$fetchedArticle['title']}\n\n內文：" . strip_tags($content);
                $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
                $answer = trim($result->text());
                if (strpos($answer, '不相關') !== false) $isRelated = false;
            } catch (\Exception $e) { $isRelated = true; }
            if (!$isRelated) {
                $this->warn('標題與內文不相關，略過: ' . $fetchedArticle['title']);
                continue;
            }

            $article = Article::create([
                'source_url' => $fetchedArticle['url'],
                'title' => $fetchedArticle['title'],
                'content' => $content,
                'image_url' => $fetchedArticle['urlToImage'],
                'summary' => $fetchedArticle['description'] ?? null,
                'category_id' => $category->id,
                'author' => $fetchedArticle['source']['name'] ?? '未知來源',
                'status' => 1,
                'published_at' => Carbon::parse($fetchedArticle['publishedAt'])->addHours(8)->setTimezone('Asia/Taipei'),
                'keywords' => $keywords,
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
                    'published_at' => Carbon::parse($articleData['published_at'])->setTimezone('Asia/Taipei'),
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