<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Article;
use Gemini\Client as GeminiClient;
use GuzzleHttp\Client as GuzzleClient;
use andreskrey\Readability\Readability;
use Stringy\Stringy;
use League\HtmlToMarkdown\HtmlConverter;
use Illuminate\Support\Facades\Cache;
use AlesZatloukal\GoogleSearchApi\GoogleSearchApi;
use App\Services\NewsDataApiService;

class ProcessArticleData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(public Article $article) {}

    public function handle(): void
    {
        \Log::info('AI 產生新聞 handle 進入時主文長度', [
            'length' => mb_strlen(strip_tags($this->article->content)),
            'sample' => mb_substr(strip_tags($this->article->content), 0, 200)
        ]);
        try {
            \Log::info('處理文章 source_url: ' . $this->article->source_url);
            
            // 檢查是否有問題的網站，直接跳過
            $problematicDomains = [
                'rfi.fr', // 法國國際廣播電台，經常回傳 403
                'bbc.com', // BBC 有時也有地區限制
            ];
            
            $urlDomain = parse_url($this->article->source_url, PHP_URL_HOST);
            foreach ($problematicDomains as $domain) {
                if (strpos($urlDomain, $domain) !== false) {
                    \Log::warning('跳過有問題的網站: ' . $urlDomain . ' for URL: ' . $this->article->source_url);
                    return;
                }
            }
            
            $guzzle = new GuzzleClient();
            $response = $guzzle->request('GET', $this->article->source_url, [
                'timeout' => 20,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
                ],
            ]);
            $finalUrl = $this->article->source_url;
            $finalPageResponse = $response;
            $html = (string) $finalPageResponse->getBody();
            if (strpos($finalUrl, 'yahoo.com') !== false) {
                \Log::info('Yahoo 來源，嘗試修正亂碼...');
                $encoding = mb_detect_encoding($html, ['UTF-8', 'BIG5', 'GBK', 'GB2312', 'ISO-8859-1'], true);
                if ($encoding && strtoupper($encoding) !== 'UTF-8') {
                    $html = mb_convert_encoding($html, 'UTF-8', $encoding);
                    \Log::info('Yahoo 來源，已轉碼: ' . $encoding . ' → UTF-8');
                }
            }
            \Log::info('原始 HTML: ' . mb_substr($html, 0, 1000));
            $this->article->source_url = $finalUrl;

            if ($response->getStatusCode() !== 200) {
                \Log::error('HTTP 狀態碼異常: ' . $response->getStatusCode() . ' for URL: ' . $this->article->source_url);
                
                if (in_array($response->getStatusCode(), [403, 404, 410, 451])) {
                    \Log::warning('遇到永久性錯誤，跳過重試: ' . $response->getStatusCode() . ' for URL: ' . $this->article->source_url);
                return;
                }
                
                throw new \Exception('HTTP 狀態碼異常: ' . $response->getStatusCode());
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
                \Log::info('XPath 抓到的內容: ' . mb_substr($rawContent, 0, 1000));
                $cleanContent = \Purifier::clean($rawContent);
                \Log::info('Purifier 清理後: ' . mb_substr($cleanContent, 0, 1000));
            } else {
                \Log::warning("XPath 沒抓到內容: " . $finalUrl);
                try {
                    $readability = new Readability($html, $finalUrl);
                    $result = $readability->init();
                    if ($result) {
                        $readContent = $readability->getContent();
                        \Log::info('Readability 抓到的內容: ' . mb_substr($readContent, 0, 1000));
                        $cleanContent = \Purifier::clean($readContent);
                        \Log::info('Purifier 清理後(Readability): ' . mb_substr($cleanContent, 0, 1000));
                    } else {
                        \Log::warning('Readability 也沒抓到內容: ' . $finalUrl);
                    }
                } catch (\Exception $e) {
                    \Log::error('Readability 解析失敗: ' . $e->getMessage());
                }
            }

            // 特殊處理：SETN 新聞網
            if (strpos($finalUrl, 'https://www.setn.com/News.aspx?NewsID=') !== false) {
                \Log::info('SETN 新聞網特殊處理: 只擷取 div#Content1 內所有 <p>');
                $content1Div = $xpath->query('//div[@id="Content1"]')->item(0);
                $setnText = [];
                if ($content1Div) {
                    foreach ($content1Div->getElementsByTagName('p') as $p) {
                        $setnText[] = trim($p->textContent);
                    }
                }
                $joined = implode("\n\n", array_filter($setnText));
                if (mb_strlen(trim($joined)) > 50) {
                    $cleanContent = nl2br(e($joined));
                    \Log::info('SETN 新聞網 Content1 內容擷取成功，長度: ' . mb_strlen($joined));
                }
            }
            // 特殊處理：UDN 新聞網
            elseif (strpos($finalUrl, 'udn.com/news') !== false) {
                // 自動轉換 UDN AMP 版網址
                if (preg_match('#^https://udn.com/news/story/(\d+)/(\d+)$#', $finalUrl, $matches)) {
                    $finalUrl = "https://udn.com/news/amp/story/{$matches[1]}/{$matches[2]}/";
                    $guzzle = new GuzzleClient();
                    $response = $guzzle->request('GET', $finalUrl, [
                        'timeout' => 20,
                        'verify' => false,
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
                        ],
                    ]);
                    $html = (string) $response->getBody();
                    $doc = new \DOMDocument();
                    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                    $xpath = new \DOMXPath($doc);
                }
                // UDN AMP 版主文擷取
                if (strpos($finalUrl, '/news/amp/story/') !== false) {
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
                        $cleanContent = $joined;
                        \Log::info('UDN AMP 內容擷取成功，長度: ' . mb_strlen($joined));
                    }
                } else {
                // 原本的 UDN 擷取流程
                \Log::info('UDN 新聞網特殊處理: 支援多個主內容 XPath');
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
                    $cleanContent = $joined;
                    \Log::info('UDN 內容擷取成功，長度: ' . mb_strlen($joined));
                }
                }
            }
            // 特殊處理：Yahoo 新聞網
            elseif (strpos($finalUrl, 'yahoo.com') !== false) {
                \Log::info('Yahoo 新聞網特殊處理: 嘗試多種選擇器');
                // 嘗試多種可能的選擇器
                $possibleSelectors = [
                    '//*[contains(@class, "atoms")]',
                    '//*[contains(@class, "caas-body")]',
                    '//*[contains(@class, "article-content")]',
                    '//*[contains(@class, "content")]',
                    '//article',
                    '//*[contains(@class, "story-body")]',
                    '//*[contains(@class, "article-body")]',
                    '//*[contains(@class, "post-body")]',
                ];
                
                $yahooText = [];
                $usedSelector = '';
                
                foreach ($possibleSelectors as $selector) {
                    $contentDiv = $xpath->query($selector)->item(0);
                    if ($contentDiv) {
                        $pElements = $contentDiv->getElementsByTagName('p');
                        if ($pElements->length > 0) {
                            foreach ($pElements as $p) {
                                $text = trim($p->textContent);
                                if (!empty($text) && mb_strlen($text) > 10) {
                                    $yahooText[] = $text;
                                }
                            }
                            $usedSelector = $selector;
                            break;
                        }
                    }
                }
                
                $joined = implode("\n\n", array_filter($yahooText));
                
                \Log::info('Yahoo content debug', [
                    'url' => $finalUrl,
                    'usedSelector' => $usedSelector,
                    'pCount' => count($yahooText),
                    'joinedLength' => mb_strlen($joined),
                    'joinedSample' => mb_substr($joined, 0, 300),
                    'yahooText' => array_slice($yahooText, 0, 3), // 只記錄前3個段落
                ]);
                
                if (mb_strlen(trim($joined)) > 50) {
                    $cleanContent = $joined;
                    \Log::info('Yahoo 新聞網內容擷取成功，長度: ' . mb_strlen($joined) . ', 選擇器: ' . $usedSelector);
                } else {
                    \Log::warning('Yahoo 新聞網內容擷取失敗，嘗試其他方法，長度: ' . mb_strlen($joined));
                }
            }
            // 特殊處理：LINE TODAY 新聞
            elseif (strpos($finalUrl, 'today.line.me/tw/v2/article') !== false) {
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
                    $cleanContent = $joined;
                    \Log::info('LINE TODAY 內容擷取成功，長度: ' . mb_strlen($joined));
                }
            }

            if (empty($cleanContent) || trim(strip_tags($cleanContent)) === '') {
                \Log::warning('主文擷取失敗，fallback 回原本 content: ' . $this->article->content);
                $cleanContent = $this->article->content ?? '內容抓取失敗。';
            }

            $plainText = trim(strip_tags($cleanContent));
            if (mb_strlen($plainText) < 100) {
                \Log::warning('主文少於100字，略過 AI 任務，article ID: ' . $this->article->id);
                return;
            }
            
            \Log::info('AI 產生新聞前主文長度', [
                'length' => mb_strlen($plainText),
                'sample' => mb_substr($plainText, 0, 200)
            ]);
            if (stripos($plainText, 'ONLY AVAILABLE IN PAID PLANS') !== false || 
                stripos($plainText, 'PAID PLANS') !== false ||
                stripos($plainText, 'PREMIUM CONTENT') !== false ||
                stripos($plainText, 'SUBSCRIBE TO READ') !== false ||
                stripos($plainText, 'PAY TO READ') !== false ||
                stripos($plainText, 'MEMBERS ONLY') !== false) {
                \Log::warning('跳過付費內容文章，article ID: ' . $this->article->id . ', URL: ' . $this->article->source_url);
                return;
            }
            if (mb_strlen($plainText) < 80) {
                \Log::warning('文章全文過短，略過 AI 生成，article ID: ' . $this->article->id);
                return;
            }

            $isRelated = true;
            try {
                $gemini = resolve(GeminiClient::class);
                $prompt = "請判斷以下新聞標題與內文是否相關，僅回傳「相關」或「不相關」：\n\n標題：{$this->article->title}\n\n內文：" . strip_tags($this->article->content);
                $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
                $answer = trim($result->text());
                if (strpos($answer, '不相關') !== false) $isRelated = false;
            } catch (\Exception $e) { $isRelated = true; }
            if (!$isRelated) {
                \Log::warning('標題與內文不相關，AI 先生成內文再生成標題: ' . $this->article->title);
                try {
                    $gemini = resolve(GeminiClient::class);
                    // 先用AI生成內文
                    $contentPrompt = "請根據以下新聞標題與原始描述，撰寫一篇約 500 字的完整新聞內容，並以 Markdown 格式輸出，請使用繁體中文。請將主體內容包在 <!--start--> 和 <!--end--> 標記之間：\n\n標題：{$this->article->title}\n\n原始描述：" . strip_tags($this->article->content);
                    $contentResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($contentPrompt);
                    $markdownContent = $contentResult->text();
                    if (preg_match('/<!--start-->(.*?)<!--end-->/s', $markdownContent, $matches)) {
                        $markdownContent = trim($matches[1]);
                    }
                    // 檢查是否為付費內容
                    $paidKeywords = ['ONLY AVAILABLE IN PAID PLANS', 'PAID PLANS', 'PREMIUM CONTENT', 'SUBSCRIBE TO READ', 'PAY TO READ', 'MEMBERS ONLY'];
                    foreach ($paidKeywords as $kw) {
                        if (stripos($markdownContent, $kw) !== false) {
                            \Log::warning('AI 生成內文為付費內容，略過: ' . $this->article->title);
                            return;
                        }
                    }
                    // 再用AI根據新內文生成標題
                    $titlePrompt = "請根據以下新聞內文，為其生成一個貼切且具體的繁體中文新聞標題，僅回傳標題本身：\n\n" . strip_tags($markdownContent);
                    $titleResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($titlePrompt);
                    $newTitle = trim($titleResult->text());
                    if ($newTitle) {
                        $this->article->title = $newTitle;
                        \Log::info('AI 生成新標題: ' . $newTitle);
                    }
                    $this->article->content = $markdownContent;
                } catch (\Exception $e) {
                    \Log::error('AI 生成新標題/內文失敗: ' . $e->getMessage());
                    return;
                }
            }
            // 檢查是否為付費內容
            $paidKeywords = ['ONLY AVAILABLE IN PAID PLANS', 'PAID PLANS', 'PREMIUM CONTENT', 'SUBSCRIBE TO READ', 'PAY TO READ', 'MEMBERS ONLY'];
            foreach ($paidKeywords as $kw) {
                if (stripos($this->article->content, $kw) !== false) {
                    \Log::warning('內文為付費內容，略過: ' . $this->article->title);
                    return;
                }
            }

            $gemini = resolve(GeminiClient::class);
            $now = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
            $prompt = "現在時間為 {$now}（UTC+8）。請根據以下新聞全文，撰寫一篇約 500 字的完整新聞內容，並以 Markdown 格式輸出，請使用繁體中文。請將主體內容包在 <!--start--> 和 <!--end--> 標記之間：\n\n" . $plainText;
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $markdownContent = $result->text();
            if (preg_match('/<!--start-->(.*?)<!--end-->/s', $markdownContent, $matches)) {
                $markdownContent = trim($matches[1]);
            }
            // 寫入前先清理第一句包含「好的」的句子
            $this->article->content = Article::cleanFirstSentence($markdownContent);

            try {
                $gemini = resolve(GeminiClient::class);
                $prompt = "請根據以下新聞內容，產生3~5個適合用於分類與推薦的繁體中文關鍵字或短語，僅回傳關鍵字本身，用逗號分隔：\n\n" . strip_tags($this->article->content);
                $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
                $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $result->text()));
                $this->article->keywords = $keywords;
            } catch (\Exception $e) {
                \Log::error('AI 產生關鍵字失敗: ' . $e->getMessage());
            }

            $prompt = "請將以下新聞內容，整理成一段約 150 字的精簡摘要，請使用繁體中文：\n\n" . strip_tags($this->article->content);
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $this->article->summary = $result->text();

            $this->performAutoCredibilityScan();

            if ($this->article->isDirty()) {
                $this->article->saveQuietly();
            }
        } catch (\Exception $e) {
            \Log::error('Job 處理失敗 for article ID: ' . $this->article->id . ' - ' . $e->getMessage());
            $this->release(60);
        }
    }

    private function performAutoCredibilityScan(): void
    {
        try {
            \Log::info('開始自動可信度掃描，article ID: ' . $this->article->id);
            $plainText = strip_tags($this->article->content);
            if (mb_strlen($plainText) < 100) {
                \Log::warning('文章內容過短，跳過可信度掃描');
                return;
            }
            $gemini = resolve(GeminiClient::class);
            $nowTime = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
            $plainTextMarked = "【主文開始】\n" . $plainText . "\n【主文結束】";
            $searchText = '';
            $searchKeyword = '';
            // 產生查證關鍵字
            try {
                $promptKeyword = "你是一個新聞查證專家。請分析以下新聞內容，提取3-5個最重要的關鍵字用於查證。\n\n請嚴格按照以下 JSON 格式回覆，不要添加任何其他文字：\n\n{\n  \"keywords\": [\"關鍵字1\", \"關鍵字2\", \"關鍵字3\", \"關鍵字4\", \"關鍵字5\"],\n  \"search_phrase\": \"關鍵字1 關鍵字2 關鍵字3\"\n}\n\n要求：\n- 關鍵字應該是名詞、人名、地名、事件名稱等具體實體\n- 搜尋短語是關鍵字的組合，用於搜尋相關新聞\n- 所有關鍵字必須是繁體中文\n\n新聞內容：\n" . $plainTextMarked;
                $resultKeyword = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($promptKeyword);
                $keywordResponse = trim($resultKeyword->text());
                $keywordData = null;
                if (preg_match('/\{.*\}/s', $keywordResponse, $matches)) {
                    $jsonStr = $matches[0];
                    $keywordData = json_decode($jsonStr, true);
                }
                if (!$keywordData || !isset($keywordData['keywords'])) {
                    $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $keywordResponse));
                    $keywordsArr = array_filter(array_map('trim', explode(',', $keywords)));
                    $searchKeyword = implode(' ', array_slice($keywordsArr, 0, 5));
                } else {
                    $searchKeyword = $keywordData['search_phrase'] ?? implode(' ', $keywordData['keywords']);
                }
            } catch (\Exception $e) {
                $searchKeyword = '';
            }
            // 站內新聞搜尋
            $articles = Article::search($searchKeyword)->take(3)->get();
            foreach ($articles as $idx => $article) {
                if ($article->id === $this->article->id) continue;
                $searchText .= ($idx+1) . ". [站內] 標題：" . $article->title . "\n";
                $searchText .= "摘要：" . ($article->summary ?: mb_substr(strip_tags($article->content),0,100)) . "\n";
                $searchText .= "來源：" . ($article->source_url ?? '') . "\n---\n";
            }
            // 外部新聞查證
            $externalUrls = [];
            $newsApiKey = env('NEWS_API_KEY');
            if ($newsApiKey) {
                try {
                    $newsResponse = (new \GuzzleHttp\Client())->get('https://newsapi.org/v2/everything', [
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
                            if (!empty($article['url'])) {
                                $externalUrls[] = $article['url'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('NewsAPI 查詢失敗', [
                        'articleId' => $this->article->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
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
                            if (!empty($article['source_url'])) {
                                $externalUrls[] = $article['source_url'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('NewsData API 查詢失敗', [
                        'articleId' => $this->article->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            try {
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
                        $searchText .= ($idx+1) . ". [Google] 標題：" . $item['title'] . "\n";
                        $searchText .= "摘要：" . ($item['snippet'] ?? '') . "\n";
                        $searchText .= "來源：" . ($item['link'] ?? '') . " \n---\n";
                        if (!empty($item['link'])) {
                            $externalUrls[] = $item['link'];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Google Search API 查詢失敗', [
                    'articleId' => $this->article->id,
                    'error' => $e->getMessage(),
                ]);
            }
            // AI 摘要每個外部網站內容
            $externalSummaries = [];
            foreach ($externalUrls as $url) {
                try {
                    $guzzle = new \GuzzleHttp\Client(['timeout' => 15, 'verify' => false]);
                    $response = $guzzle->get($url, [
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                        ],
                    ]);
                    $html = (string) $response->getBody();
                    $doc = new \DOMDocument();
                    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                    $xpath = new \DOMXPath($doc);
                    $contentNode = $xpath->query(
                        '//article | //*[contains(@class, "article-content")] | //*[contains(@class, "post-body")] | //*[contains(@class, "entry-content")] | //*[contains(@class, "caas-body")] | //*[contains(@class, "main-content")] | //*[contains(@class, "article-body")] | //*[contains(@id, "paragraph")] | //*[contains(@class, "content")] | //*[contains(@class, "post_content")]'
                    )->item(0);
                    $mainText = '';
                    if ($contentNode) {
                        $mainText = trim(strip_tags($doc->saveHTML($contentNode)));
                    } else {
                        $mainText = trim(strip_tags($html));
                    }
                    // 2. AI 摘要主文
                    if (mb_strlen($mainText) > 20) {
                        $summaryPrompt = "請摘要這個網頁的主要內容（繁體中文，200字內）：\n" . mb_substr($mainText, 0, 3000);
                    } else {
                        $summaryPrompt = "請摘要這個網頁的主要內容（繁體中文，200字內）：\n" . $url;
                    }
                    $summaryResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($summaryPrompt);
                    $externalSummaries[$url] = trim($summaryResult->text());
                } catch (\Exception $e) {
                    $externalSummaries[$url] = '（AI 摘要失敗）';
                }
            }
            if (!empty($externalSummaries)) {
                $searchText .= "\n\n【AI 網頁摘要】\n";
                foreach ($externalSummaries as $url => $summary) {
                    $searchText .= "來源：$url\n摘要：$summary\n---\n";
                }
            }
            if (!$searchText) {
                $searchText = '（查無相關新聞）';
            }
            // AI 綜合查證
            $prompt = "你是一個新聞可信度分析專家。請根據提供的查證資料，分析用戶輸入的新聞內容的可信度。\n\n請嚴格按照以下 JSON 格式回覆，不要添加任何其他文字：\n\n{\n  \"analysis\": \"詳細的查證過程和可信度分析理由\",\n  \"credibility_score\": 85,\n  \"recommendation\": \"對讀者的建議和提醒\",\n  \"sources\": [\"查證來源1\", \"查證來源2\", \"查證來源3\"]\n}\n\n要求：\n- credibility_score: 0-100 的整數，代表可信度百分比\n- analysis: 詳細說明查證過程和判斷理由\n- recommendation: 給讀者的具體建議\n- sources: 列出所有查證時參考的來源\n\n查證時間：{$nowTime}\n\n查證資料：\n{$searchText}\n\n待查證新聞：\n{$plainTextMarked}\n\n資料來源：用戶貼上主文\n查證關鍵字：{$searchKeyword}";
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $aiResponse = trim($result->text());
            // 嘗試解析 JSON 回應
            $analysisData = null;
            $jsonStr = null;
            if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $aiResponse, $matches)) {
                $jsonStr = $matches[0];
            } elseif (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
                $jsonStr = $matches[0];
            }
            if ($jsonStr) {
                $analysisData = json_decode($jsonStr, true);
            }
            if (!$analysisData || json_last_error() !== JSON_ERROR_NONE) {
                // 回退到原本格式
                $fallbackPrompt = "請參考下列新聞資料，針對用戶輸入的內容（主文已用【主文開始】與【主文結束】標記）進行查證，並以繁體中文簡要說明查證過程與理由，最後請獨立一行以【可信度：xx%】格式標示可信度，再給出建議。請將主文原文用【主文開始】與【主文結束】標記包住。所有網址連結結束處請加上一個空格。請在回應最後以**【查證出處】**區塊列出所有引用的網站、新聞來源或資料連結。\n\n【查證時間：{$nowTime}】\n\n【新聞資料】\n" . $searchText . "\n【用戶輸入】\n" . $plainTextMarked . "\n\n---\n資料來源：用戶貼上主文\n查證關鍵字：{$searchKeyword}";
                $fallbackResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($fallbackPrompt);
                $aiText = trim($fallbackResult->text());
            } else {
                $aiText = $analysisData['analysis'] . "\n\n【可信度：" . $analysisData['credibility_score'] . "%】\n\n" . $analysisData['recommendation'] . "\n\n【查證出處】\n" . implode("\n", $analysisData['sources']);
            }
            $credibilityScore = null;
            if (preg_match('/【可信度：(\d+)%】/', $aiText, $matches)) {
                $credibilityScore = (int) $matches[1];
            }
            $this->article->credibility_analysis = $aiText;
            $this->article->credibility_score = $credibilityScore;
            $this->article->credibility_checked_at = now();
            \Log::info('自動可信度掃描完成', [
                'articleId' => $this->article->id,
                'credibilityScore' => $credibilityScore,
            ]);
        } catch (\Exception $e) {
            \Log::error('自動可信度掃描失敗', [
                'articleId' => $this->article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractCredibilityScore(string $aiText): ?int
    {
        if (preg_match('/【可信度：(\d+)%】/', $aiText, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
    }