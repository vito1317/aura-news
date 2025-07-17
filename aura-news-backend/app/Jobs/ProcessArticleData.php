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

class ProcessArticleData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Article $article) {}

    public function handle(): void
    {
        try {
            \Log::info('處理文章 source_url: ' . $this->article->source_url);
            $guzzle = new GuzzleClient();
            $response = $guzzle->request('GET', $this->article->source_url, [
                'timeout' => 20,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36'
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
                return;
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
                // 嘗試用 Readability 萃取主文
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

            // Yahoo News 特殊處理：只抓 div.atoms 內所有 <p> 的純文字
            if (preg_match('/https?:\/\/(?:[\w-]+\.)*yahoo\.com\//i', $finalUrl)) {
                \Log::info('Yahoo News 特殊處理: 只擷取 div.atoms 內所有 <p>');
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
                    \Log::info('Yahoo News .atoms 內容擷取成功，長度: ' . mb_strlen($joined));
                }
            }

            // fallback: 如果 XPath/Readability 都沒抓到，回原本 content
            if (empty($cleanContent) || trim(strip_tags($cleanContent)) === '') {
                \Log::warning('主文擷取失敗，fallback 回原本 content: ' . $this->article->content);
                $cleanContent = $this->article->content ?? '內容抓取失敗。';
            }

            // 若全文過短則略過 AI 生成
            $plainText = trim(strip_tags($cleanContent));
            if (mb_strlen($plainText) < 100) {
                \Log::warning('新聞全文過短，略過 AI 生成，article ID: ' . $this->article->id);
                return;
            }

            $gemini = resolve(GeminiClient::class);
            // AI 先根據全文生成完整內容（Markdown），主體包在 <!--start--> 和 <!--end--> 標記
            $prompt = "請根據以下新聞全文，撰寫一篇約 500 字的完整新聞內容，並以 Markdown 格式輸出，請使用繁體中文。請將主體內容包在 <!--start--> 和 <!--end--> 標記之間：\n\n" . $plainText;
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $markdownContent = $result->text();
            // 只保留 <!--start--> 和 <!--end--> 之間的內容
            if (preg_match('/<!--start-->(.*?)<!--end-->/s', $markdownContent, $matches)) {
                $markdownContent = trim($matches[1]);
            }
            $this->article->content = $markdownContent;

            // 再根據 AI 生成的內容產生摘要
            $prompt = "請將以下新聞內容，整理成一段約 150 字的精簡摘要，請使用繁體中文：\n\n" . strip_tags($this->article->content);
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $this->article->summary = $result->text();

            if ($this->article->isDirty()) {
                $this->article->saveQuietly();
            }
        } catch (\Exception $e) {
            \Log::error('Job 處理失敗 for article ID: ' . $this->article->id . ' - ' . $e->getMessage());
            $this->release(60);
        }
    }
}