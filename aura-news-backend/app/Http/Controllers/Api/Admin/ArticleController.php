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
        // 強制分頁 path 為 https
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
        ]);

        $article = Article::create(array_merge($validatedData, [
            'source_url' => 'https://source.placeholder.com/' . now()->timestamp,
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
                    $cleanContent = nl2br(e($joined));
                }
            }
            if (!$cleanContent) {
                $contentNode = $xpath->query(
                    '//article | //*[contains(@class, "article-content")] | //*[contains(@class, "post-body")] | //*[contains(@class, "entry-content")] | //*[contains(@class, "caas-body")] | //*[contains(@class, "main-content")] | //*[contains(@class, "article-body")] | //*[contains(@id, "paragraph")] | //*[contains(@class, "content")] | //*[contains(@class, "post_content")]'
                )->item(0);
                if ($contentNode) {
                    $rawContent = $doc->saveHTML($contentNode);
                    $cleanContent = \Purifier::clean($rawContent);
                }
            }
            if (!$cleanContent) {
                $bodyNode = $xpath->query('//body')->item(0);
                if ($bodyNode) {
                    $cleanContent = \Purifier::clean($doc->saveHTML($bodyNode));
                }
            }
        } catch (\Exception $e) {
            return response()->json(['message' => '主文擷取失敗'], 422);
        }
        $plainText = trim(strip_tags($cleanContent));
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
        ]);
    }
}