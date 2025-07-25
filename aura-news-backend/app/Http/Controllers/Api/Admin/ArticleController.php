<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::latest()
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->whereRaw('CHAR_LENGTH(content) >= 500')
            ->paginate(10);
        $articles->setPath(preg_replace('/^http:/', 'https:', $articles->path()));
        return response()->json($articles);
    }

    public function store(Request $request)
    {

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'nullable|string',
            'status' => ['required', Rule::in([1, 2, 3])],
            'image_url' => 'nullable|string|url',
            'category_id' => 'required|exists:categories,id',
            'source_url' => 'nullable|string|url',
            'keywords' => 'nullable|string',
        ]);

        // 寫入前先清理第一句包含「好的」的句子
        $validatedData['content'] = \App\Models\Article::cleanFirstSentence($validatedData['content']);
        $popularity = $validatedData['popularity_score'] ?? 0;
        $viewCount = intval($popularity * 20);
        $article = Article::create(array_merge($validatedData, [
            'source_url' => $validatedData['source_url'] ?? null,
            'author' => 'Admin',
            'published_at' => now(),
            'keywords' => $validatedData['keywords'] ?? null,
            'view_count' => $viewCount,
        ]));

        // 自動進行可信度掃描（略過內容類型偵測）
        try {
            $content = $article->content;
            $taskId = 'admin_store_' . $article->id . '_' . uniqid();
            $clientIp = request()->ip() ?? '127.0.0.1';
            // 直接呼叫可信度分析主流程（略過內容類型偵測）
            $credibilityResult = $this->runCredibilityScan($content, $taskId, $clientIp);
            if ($credibilityResult) {
                $article->credibility_analysis = $credibilityResult['analysis_result'];
                $article->credibility_score = $credibilityResult['credibility_score'];
                $article->credibility_checked_at = now();
                $article->save();
            }
        } catch (\Exception $e) {
            \Log::error('自動可信度掃描失敗', ['error' => $e->getMessage()]);
        }

        return response()->json($article, 201);
    }

    public function show(Article $article)
    {
        return response()->json($article);
    }

    public function update(Request $request, Article $article)
    {

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'nullable|string',
            'status' => ['required', Rule::in([1, 2, 3])],
            'image_url' => 'nullable|string|url',
            'category_id' => 'required|exists:categories,id',
            'keywords' => 'nullable|string',
        ]);
        // 寫入前先清理第一句包含「好的」的句子
        $validatedData['content'] = \App\Models\Article::cleanFirstSentence($validatedData['content']);
        $article->update($validatedData);
        return response()->json($article);
    }

    public function destroy(Article $article)
    {
        try {
            $article->delete();
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json(['message' => '刪除文章時發生錯誤'], 500);
        }
    }

    public function aiGenerate(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
        ]);
        $url = $request->input('url');
        try {
            $guzzle = new \GuzzleHttp\Client(['timeout' => 20, 'verify' => false]);
            $response = $guzzle->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36'
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                return response()->json(['message' => '無法取得該網址內容'], 422);
            }
            $html = (string) $response->getBody();
        } catch (\Exception $e) {
            return response()->json(['message' => '無法下載該網址內容'], 422);
        }
        $title = null;
        $image_url = null;
        $category_id = null;
        $cleanContent = null;
        
        \Log::info('開始解析 HTML', [
            'url' => $url,
            'html_length' => strlen($html),
            'html_preview' => substr($html, 0, 500)
        ]);
        
        try {
            $doc = new \DOMDocument();
            @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            $xpath = new \DOMXPath($doc);
            $titleNode = $xpath->query('//title')->item(0);
            if ($titleNode) $title = trim($titleNode->textContent);
            $imgNode = $xpath->query('//meta[@property="og:image"]')->item(0);
            if ($imgNode && $imgNode->getAttribute('content')) {
                $image_url = $imgNode->getAttribute('content');
            }
            \Log::info('基本資訊擷取', [
                'title' => $title,
                'image_url' => $image_url
            ]);
            // SETN 新聞網
            if (strpos($url, 'setn.com/News.aspx?NewsID=') !== false) {
                $content1Div = $xpath->query('//div[@id="Content1"]')->item(0);
                $setnText = [];
                if ($content1Div) {
                    foreach ($content1Div->getElementsByTagName('p') as $p) {
                        $setnText[] = trim($p->textContent);
                    }
                }
                $joined = implode("\n\n", array_filter($setnText));
                if (mb_strlen(trim($joined)) > 50) {
                    $cleanContent = $joined;
                    \Log::info('SETN 新聞網 Content1 內容擷取成功，長度: ' . mb_strlen($joined));
                }
            }
            // UDN 新聞網
            elseif (strpos($url, 'udn.com/news') !== false) {
                // AMP 轉換
                if (preg_match('#^https://udn.com/news/story/(\\d+)/(\\d+)$#', $url, $matches)) {
                    $url = "https://udn.com/news/amp/story/{$matches[1]}/{$matches[2]}/";
                    $guzzle = new \GuzzleHttp\Client(['timeout' => 20, 'verify' => false]);
                    $response = $guzzle->get($url, [
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
                if (strpos($url, '/news/amp/story/') !== false) {
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
            // Yahoo 新聞網
            elseif (strpos($url, 'yahoo.com') !== false) {
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
                    'url' => $url,
                    'usedSelector' => $usedSelector,
                    'pCount' => count($yahooText),
                    'joinedLength' => mb_strlen($joined),
                    'joinedSample' => mb_substr($joined, 0, 300),
                    'yahooText' => array_slice($yahooText, 0, 3),
                ]);
                if (mb_strlen(trim($joined)) > 50) {
                    $cleanContent = $joined;
                    \Log::info('Yahoo 新聞網內容擷取成功', [
                        'joinedLength' => mb_strlen($joined),
                        'selector' => $usedSelector,
                    ]);
                } else {
                    \Log::warning('Yahoo 新聞網內容擷取失敗，嘗試其他方法', [
                        'joinedLength' => mb_strlen($joined),
                    ]);
                }
            }
            // LINE TODAY 新聞
            elseif (strpos($url, 'today.line.me/tw/v2/article') !== false) {
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
            // 一般處理
            if (!$cleanContent) {
                $contentNode = $xpath->query(
                    '//article | //*[contains(@class, "article-content")] | //*[contains(@class, "post-body")] | //*[contains(@class, "entry-content")] | //*[contains(@class, "caas-body")] | //*[contains(@class, "main-content")] | //*[contains(@class, "article-body")] | //*[contains(@id, "paragraph")] | //*[contains(@class, "content")] | //*[contains(@class, "post_content")]'
                )->item(0);
                if ($contentNode) {
                    $rawContent = $doc->saveHTML($contentNode);
                    $cleanContent = \Purifier::clean($rawContent);
                    \Log::info('一般內容擷取成功', [
                        'content_node_tag' => $contentNode->tagName,
                        'raw_content_length' => strlen($rawContent),
                        'clean_content_length' => strlen($cleanContent)
                    ]);
                } else {
                    \Log::warning('未找到內容節點');
                }
            }
            if (!$cleanContent) {
                $bodyNode = $xpath->query('//body')->item(0);
                if ($bodyNode) {
                    $cleanContent = \Purifier::clean($doc->saveHTML($bodyNode));
                    \Log::info('使用 body 節點', [
                        'body_content_length' => strlen($cleanContent)
                    ]);
                } else {
                    \Log::warning('未找到 body 節點');
                }
            }
        } catch (\Exception $e) {
            \Log::error('主文擷取失敗', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => '主文擷取失敗: ' . $e->getMessage()], 422);
        }
        $plainText = trim(strip_tags($cleanContent));
        // 過濾主文少於100字的文章
        if (mb_strlen($plainText) < 100) {
            return response()->json(['message' => '主文內容過短（少於100字），無法產生新聞稿'], 422);
        }
        // 主文最後加上原文出處
        $sourceUrl = $request->input('source_url') ?? $url;
        if ($plainText && preg_match('/^https?:\/\//i', trim($sourceUrl))) {
            $plainText .= "\n\n---\n資料來源：<a href='" . trim($sourceUrl) . "' target='_blank' rel='noopener noreferrer'>" . trim($sourceUrl) . "</a>";
        }
        if (mb_strlen($plainText) < 100) {
            return response()->json(['message' => '主文內容過短，無法產生新聞稿'], 422);
        }
        try {
            $gemini = resolve(\Gemini\Client::class);
            $prompt = "請根據以下新聞全文，撰寫一篇約 500 字的完整新聞內容，並以 Markdown 格式輸出，請使用繁體中文。請將主體內容包在 <!--start--> 和 <!--end--> 標記之間：\n\n" . $plainText;
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $markdownContent = $result->text();
            if (preg_match('/<!--start-->(.*?)<!--end-->/s', $markdownContent, $matches)) {
                $markdownContent = trim($matches[1]);
            }
            $prompt = "請將以下新聞內容，整理成一段約 150 字的精簡摘要，請使用繁體中文：\n\n" . strip_tags($markdownContent);
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $summary = $result->text();
        } catch (\Exception $e) {
            return response()->json(['message' => 'AI 產生失敗: ' . $e->getMessage()], 500);
        }
        // 產生關鍵字
        $keywords = null;
        try {
            $gemini = resolve(\Gemini\Client::class);
            $prompt = "請根據以下新聞內容，產生3~5個適合用於分類與推薦的繁體中文關鍵字或短語，僅回傳關鍵字本身，用逗號分隔：\n\n" . strip_tags($markdownContent);
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $result->text()));
        } catch (\Exception $e) {
            $keywords = null;
        }
        // 檢查標題與內文相關性，不相關則用 AI 生成新標題
        try {
            $gemini = resolve(\Gemini\Client::class);
            $prompt = "請判斷以下新聞標題與內文是否相關，僅回傳「相關」或「不相關」：\n\n標題：{$title}\n\n內文：" . strip_tags($markdownContent);
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $answer = trim($result->text());
            if (strpos($answer, '不相關') !== false) {
                // 先用AI生成內文
                $contentPrompt = "請根據以下新聞標題與原始描述，撰寫一篇約 500 字的完整新聞內容，並以 Markdown 格式輸出，請使用繁體中文。請將主體內容包在 <!--start--> 和 <!--end--> 標記之間：\n\n標題：{$title}\n\n原始描述：" . strip_tags($markdownContent);
                $contentResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($contentPrompt);
                $markdownContent = $contentResult->text();
                if (preg_match('/<!--start-->(.*?)<!--end-->/s', $markdownContent, $matches)) {
                    $markdownContent = trim($matches[1]);
                }
                // 檢查是否為付費內容
                $paidKeywords = ['ONLY AVAILABLE IN PAID PLANS', 'PAID PLANS', 'PREMIUM CONTENT', 'SUBSCRIBE TO READ', 'PAY TO READ', 'MEMBERS ONLY'];
                foreach ($paidKeywords as $kw) {
                    if (stripos($markdownContent, $kw) !== false) {
                        return response()->json(['message' => '主文內容為付費內容，無法產生新聞稿'], 422);
                    }
                }
                // 再用AI根據新內文生成標題
                $titlePrompt = "請根據以下新聞內文，為其生成一個貼切且具體的繁體中文新聞標題，僅回傳標題本身：\n\n" . strip_tags($markdownContent);
                $titleResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($titlePrompt);
                $newTitle = trim($titleResult->text());
                if ($newTitle) {
                    $title = $newTitle;
                }
            }
        } catch (\Exception $e) {}
        // 檢查是否為付費內容
        $paidKeywords = ['ONLY AVAILABLE IN PAID PLANS', 'PAID PLANS', 'PREMIUM CONTENT', 'SUBSCRIBE TO READ', 'PAY TO READ', 'MEMBERS ONLY'];
        foreach ($paidKeywords as $kw) {
            if (stripos($markdownContent, $kw) !== false) {
                return response()->json(['message' => '主文內容為付費內容，無法產生新聞稿'], 422);
            }
        }
        return response()->json([
            'title' => $title,
            'content' => $markdownContent,
            'summary' => $summary,
            'image_url' => $image_url,
            'category_id' => $category_id,
            'source_url' => $url, // 回傳原始網址
            'keywords' => $keywords,
        ]);
    }

    /**
     * 直接呼叫 AIScanFakeNewsJob 可信度分析主流程，略過內容類型偵測
     */
    private function runCredibilityScan($content, $taskId, $clientIp)
    {
        // 直接複製 AIScanFakeNewsJob 可信度分析主流程（略過內容類型偵測）
        $gemini = resolve(\Gemini\Client::class);
        $nowTime = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
        $plainTextMarked = "【主文開始】\n" . $content . "\n【主文結束】";
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
        $articles = \App\Models\Article::search($searchKeyword)->take(3)->get();
        $externalUrls = [];
        foreach ($articles as $idx => $article) {
            $searchText .= ($idx+1) . ". [站內] 標題：" . $article->title . "\n";
            $searchText .= "摘要：" . ($article->summary ?: mb_substr(strip_tags($article->content),0,100)) . "\n";
            $searchText .= "來源：" . ($article->source_url ?? '') . "\n---\n";
        }
        // 外部新聞查證
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
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $newsDataApiKey = env('NEWSDATA_API_KEY');
        if ($newsDataApiKey) {
            try {
                $newsDataService = new \App\Services\NewsDataApiService();
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
        // 解析可信度分數
        $credibilityScore = null;
        if (preg_match('/【可信度：(\d+)%】/', $aiText, $matches)) {
            $credibilityScore = (int) $matches[1];
        }
        return [
            'analysis_result' => $aiText,
            'credibility_score' => $credibilityScore,
        ];
    }
}