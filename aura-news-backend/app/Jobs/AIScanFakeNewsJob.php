<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Gemini\Client as GeminiClient;
use GuzzleHttp\Client as GuzzleClient;
use andreskrey\Readability\Readability;
use App\Models\Article;
use andreskrey\Readability\Configuration;
use AlesZatloukal\GoogleSearchApi\GoogleSearchApi;
use App\Services\NewsDataApiService;

class AIScanFakeNewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $taskId;
    protected $content;
    protected $clientIp;
    protected $articleId; // 新增：文章 ID

    public function __construct($taskId, $content, $clientIp, $articleId = null)
    {
        $this->taskId = $taskId;
        $this->content = $content;
        $this->clientIp = $clientIp;
        $this->articleId = $articleId;
    }

    public function handle()
    {
        $ip = $this->clientIp ?? 'unknown';
        if ($ip === '180.218.164.204') {
        } else {
            $rateKey = 'ai_scan_rate_limit_' . $ip;
            $count = Cache::get($rateKey, 0);
            if ($count >= 10) {
                \Log::info('AIScanFakeNewsJob rate limit blocked', ['ip' => $ip, 'taskId' => $this->taskId]);
                Cache::put("ai_scan_progress_{$this->taskId}", [
                    'progress' => '請求過於頻繁，請稍後再試',
                    'result' => null,
                ], 600);
                return;
            }
            Cache::put($rateKey, $count + 1, now()->addHour());
        }

        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '正在抓取主文/內容',
            'result' => null,
        ], 600);
        sleep(1);
        $plainText = null;
        if (preg_match('/^https?:\/\//i', trim($this->content))) {
            $url = trim($this->content);
            try {
                $guzzle = new GuzzleClient();
                $response = $guzzle->request('GET', $url, [
                    'timeout' => 20,
                    'verify' => false,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36'
                    ],
                ]);
                $html = (string) $response->getBody();
                if (strpos($url, 'yahoo.com') !== false) {
                    $encoding = mb_detect_encoding($html, ['UTF-8', 'BIG5', 'GBK', 'GB2312', 'ISO-8859-1'], true);
                    if ($encoding && strtoupper($encoding) !== 'UTF-8') {
                        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
                    }
                }
                $doc = new \DOMDocument();
                @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                $xpath = new \DOMXPath($doc);
                $contentNode = $xpath->query(
                    '//article | //*[contains(@class, "article-content")] | //*[contains(@class, "post-body")] | //*[contains(@class, "entry-content")] | //*[contains(@class, "caas-body")] | //*[contains(@class, "main-content")] | //*[contains(@class, "article-body")] | //*[contains(@id, "paragraph")] | //*[contains(@class, "content")] | //*[contains(@class, "post_content")]'
                )->item(0);
                $cleanContent = null;
                if ($contentNode) {
                    $rawContent = $doc->saveHTML($contentNode);
                    $cleanContent = strip_tags($rawContent);
                } else {
                    $config = new Configuration();
                    $readability = new Readability($config);
                    $readability->parse($html, $url);
                    $readContent = $readability->getContent();
                    if ($readContent) {
                        $cleanContent = strip_tags($readContent);
                    }
                }
                if (preg_match('/https?:\/\/(?:[\w-]+\.)*yahoo\.com\//i', $url)) {
                    $atomsDiv = $xpath->query('//*[contains(@class, "atoms")]')->item(0);
                    $yahooText = [];
                    if ($atomsDiv) {
                        foreach ($atomsDiv->getElementsByTagName('p') as $p) {
                            $yahooText[] = trim($p->textContent);
                        }
                    }
                    $joined = implode("\n\n", array_filter($yahooText));
                    if (mb_strlen(trim($joined)) > 50) {
                        $cleanContent = $joined;
                    }
                }
                if (empty($cleanContent) || trim(strip_tags($cleanContent)) === '') {
                    $cleanContent = null;
                }
                $plainText = $cleanContent ? trim(strip_tags($cleanContent)) : null;
            } catch (\Exception $e) {
                $plainText = null;
                \Log::error('AIScanFakeNewsJob exception', [
                    'ip' => $ip,
                    'taskId' => $this->taskId,
                    'exception' => $e->getMessage(),
                ]);
            }
        } else {
            $plainText = trim($this->content);
        }

        // 若主文擷取失敗或內容過短，給予提示並結束
        if (!$plainText || $plainText === '內容抓取失敗。' || mb_strlen($plainText) < 300) {
            \Log::warning('AIScanFakeNewsJob failed', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'reason' => '主文擷取失敗或內容過短',
                'plainTextLength' => mb_strlen($plainText),
            ]);
            Cache::put("ai_scan_progress_{$this->taskId}", [
                'progress' => '主文擷取失敗，請嘗試複製主文內容貼上',
                'result' => null,
            ], 600);
            return;
        }

        // 2. AI 產生查證關鍵字並搜尋站內新聞
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 產生搜尋關鍵字',
            'result' => null,
        ], 600);
        $gemini = resolve(GeminiClient::class);
        $now = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
        $plainTextMarked = "【主文開始】\n" . $plainText . "\n【主文結束】";
        $promptKeyword = "請根據以下新聞內容（主文已用【主文開始】與【主文結束】標記），產生3~5個適合用於查證的繁體中文關鍵字或短語，僅回傳關鍵字本身，用逗號分隔：\n\n" . $plainTextMarked;
        $resultKeyword = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($promptKeyword);
        $keywords = $resultKeyword->text();
        $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $keywords));
        $keywordsArr = array_filter(array_map('trim', explode(',', $keywords)));
        $searchKeyword = implode(' ', array_slice($keywordsArr, 0, 5));

        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '正在搜尋新聞資料',
            'result' => null,
        ], 600);
        sleep(1);
        // 1. 站內新聞搜尋
        $articles = Article::search($searchKeyword)->take(3)->get();
        $searchText = '';
        foreach ($articles as $idx => $article) {
            // 如果是文章可信度分析，排除自己
            if ($this->articleId && $article->id == $this->articleId) {
                continue;
            }
            $searchText .= ($idx+1) . ". [站內] 標題：" . $article->title . "\n";
            $searchText .= "摘要：" . ($article->summary ?: mb_substr(strip_tags($article->content),0,100)) . "\n";
            $searchText .= "來源：" . ($article->source_url ?? '') . "\n---\n";
        }
        // 2. NewsAPI 全網新聞搜尋
        $newsApiKey = env('NEWS_API_KEY');
        $newsApiUrl = 'https://newsapi.org/v2/everything';
        if ($newsApiKey) {
            try {
                $newsResponse = (new \GuzzleHttp\Client())->get($newsApiUrl, [
                    'query' => [
                        'q' => $searchKeyword,
                        'language' => 'zh',
                        'sortBy' => 'publishedAt',
                        'apiKey' => $newsApiKey,
                        'pageSize' => 3,
                    ],
                    'timeout' => 10,
                    'verify' => false,
                ]);
                $newsData = json_decode($newsResponse->getBody(), true);
                if (!empty($newsData['articles'])) {
                    foreach ($newsData['articles'] as $idx => $article) {
                        $searchText .= ($idx+1) . ". [NewsAPI] 標題：" . $article['title'] . "\n";
                        $searchText .= "摘要：" . ($article['description'] ?? '') . "\n";
                        $searchText .= "來源：" . ($article['url'] ?? '') . "\n---\n";
                    }
                }
            } catch (\Exception $e) {
                \Log::error('NewsAPI 查詢失敗', [
                    'taskId' => $this->taskId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. NewsData API 全網新聞搜尋
        $newsDataApiKey = env('NEWSDATA_API_KEY');
        if ($newsDataApiKey) {
            try {
                $newsDataService = new NewsDataApiService();
                $newsDataArticles = $newsDataService->searchArticles($searchKeyword, 'zh', 3);
                
                if ($newsDataArticles && $newsDataArticles->count() > 0) {
                    foreach ($newsDataArticles as $idx => $article) {
                        $searchText .= ($idx+1) . ". [NewsData] 標題：" . $article['title'] . "\n";
                        $searchText .= "摘要：" . ($article['summary'] ?? '') . "\n";
                        $searchText .= "來源：" . ($article['source_url'] ?? '') . "\n---\n";
                    }
                }
            } catch (\Exception $e) {
                \Log::error('NewsData API 查詢失敗', [
                    'taskId' => $this->taskId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. Google Custom Search
        $googleSearchText = '';
        try {
            \Log::info('GoogleSearchApi config debug', [
                'engineId' => config('googlesearchapi.google_search_engine_id'),
                'apiKey' => config('googlesearchapi.google_search_api_key'),
            ]);
            $client = new \GuzzleHttp\Client();
            $response = $client->get('https://www.googleapis.com/customsearch/v1', [
                'query' => [
                    'key' => config('googlesearchapi.google_search_api_key'),
                    'cx' => config('googlesearchapi.google_search_engine_id'),
                    'q' => $searchKeyword,
                    'num' => 3,
                    'lr' => 'lang_zh-TW',
                    'safe' => 'off',
                ],
                'timeout' => 10,
                'verify' => false,
            ]);
            $googleData = json_decode($response->getBody(), true);
            if (!empty($googleData['items'])) {
                foreach ($googleData['items'] as $idx => $item) {
                    $googleSearchText .= ($idx+1) . ". [Google] 標題：" . $item['title'] . "\n";
                    $googleSearchText .= "摘要：" . ($item['snippet'] ?? '') . "\n";
                    $googleSearchText .= "來源：" . ($item['link'] ?? '') . " \n---\n";
                }
            }
        } catch (\Exception $e) {
            \Log::error('Google Search API 查詢失敗', [
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
        }
        $searchText .= $googleSearchText;
        if (!$searchText) {
            $searchText = '（查無相關新聞）';
        }

        // 3. AI 綜合查證
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 綜合查證中',
            'result' => null,
        ], 600);
        $nowTime = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
        $prompt = "請參考下列新聞資料，針對用戶輸入的內容（主文已用【主文開始】與【主文結束】標記）進行查證，並以繁體中文簡要說明查證過程與理由，最後請獨立一行以【可信度：xx%】格式標示可信度，再給出建議。請將主文原文用【主文開始】與【主文結束】標記包住。所有網址連結結束處請加上一個空格。請在回應最後以**【查證出處】**區塊列出所有引用的網站、新聞來源或資料連結。\n\n【查證時間：{$nowTime}】\n\n【新聞資料】\n" . $searchText . "\n【用戶輸入】\n" . $plainTextMarked . "\n\n---\n資料來源：" . (preg_match('/^https?:\/\//i', trim($this->content)) ? $this->content : '用戶貼上主文') . "\n查證關鍵字：" . $searchKeyword;
        $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
        $aiText = $result->text();

        // 完成
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '完成',
            'result' => $aiText,
        ], 600);

        // 如果是文章可信度分析，自動更新文章資訊
        if ($this->articleId) {
            $this->updateArticleCredibility($aiText);
        }
    }

    /**
     * 更新文章的可信度資訊
     */
    private function updateArticleCredibility($analysisResult)
    {
        try {
            $article = Article::find($this->articleId);
            if (!$article) {
                \Log::error('找不到對應的文章', ['articleId' => $this->articleId]);
                return;
            }

            // 提取可信度分數
            $credibilityScore = $this->extractCredibilityScore($analysisResult);
            
            // 更新文章
            $article->update([
                'credibility_analysis' => $analysisResult,
                'credibility_score' => $credibilityScore,
                'credibility_checked_at' => now(),
            ]);

            \Log::info('文章可信度分析完成並更新', [
                'article_id' => $article->id,
                'credibility_score' => $credibilityScore,
            ]);

        } catch (\Exception $e) {
            \Log::error('更新文章可信度失敗', [
                'articleId' => $this->articleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 從 AI 回應中提取可信度百分比
     */
    private function extractCredibilityScore(string $aiText): ?int
    {
        if (preg_match('/【可信度：(\d+)%】/', $aiText, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
} 