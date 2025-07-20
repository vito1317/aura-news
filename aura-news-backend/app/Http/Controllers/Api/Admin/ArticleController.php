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
        ]);

        $article = Article::create(array_merge($validatedData, [
            'source_url' => $validatedData['source_url'] ?? null,
            'author' => 'Admin',
            'published_at' => now(),
        ]));

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
        ]);

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
        // 主文最後加上原文出處
        if ($plainText && preg_match('/^https?:\/\//i', trim($url))) {
            $plainText .= "\n\n---\n資料來源：<a href='" . trim($url) . "' target='_blank' rel='noopener noreferrer'>" . trim($url) . "</a>";
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
        return response()->json([
            'title' => $title,
            'content' => $markdownContent,
            'summary' => $summary,
            'image_url' => $image_url,
            'category_id' => $category_id,
            'source_url' => $url, // 回傳原始網址
        ]);
    }
}