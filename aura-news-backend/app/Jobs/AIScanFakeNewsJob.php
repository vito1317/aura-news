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
use App\Services\BraveSearchService;
use App\Models\AiScanResult;

class AIScanFakeNewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $taskId;
    protected $content;
    protected $clientIp;
    protected $articleId;

    public function __construct($taskId, $content, $clientIp, $articleId = null)
    {
        $this->taskId = $taskId;
        $this->content = $content;
        $this->clientIp = $clientIp;
        $this->articleId = $articleId;
    }

    private function getOldDetectionData() {
        $old = \Cache::get("ai_scan_progress_{$this->taskId}");
        return $old['detectionData'] ?? null;
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
                    'detectionData' => $this->getOldDetectionData(),
                ], 600);
                return;
            }
            Cache::put($rateKey, $count + 1, now()->addHour());
        }

        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '正在抓取主文/內容',
            'result' => null,
            'detectionData' => $this->getOldDetectionData(),
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
                // 特殊處理：SETN 新聞網
                if (strpos($url, 'https://www.setn.com/News.aspx?NewsID=') !== false) {
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
                        \Log::info('SETN 新聞網 Content1 內容擷取成功', [
                            'taskId' => $this->taskId,
                            'contentLength' => mb_strlen($joined),
                        ]);
                    }
                }
                // 特殊處理：UDN 新聞網
                elseif (strpos($url, 'udn.com/news') !== false) {
                    // 自動轉換 UDN AMP 版網址
                    if (preg_match('#^https://udn.com/news/story/(\d+)/(\d+)$#', $url, $matches)) {
                        $url = "https://udn.com/news/amp/story/{$matches[1]}/{$matches[2]}/";
                        \Log::info('自動轉換 UDN AMP 版網址', ['amp_url' => $url]);
                    }
                    $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
                    try {
                        $res = $client->get($url, [
                            'headers' => [
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                                'Cookie' => 'udn_papercookie=1;',
                                'Accept-Language' => 'zh-TW,zh;q=0.9',
                            ],
                        ]);
                        $html = (string) $res->getBody();
                        \Log::info('UDN raw HTML', ['html_sample' => mb_substr($html, 0, 1000)]);
                        $doc = new \DOMDocument();
                        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                        $xpath = new \DOMXPath($doc);
                        // log 所有 div 的 class
                        foreach ($doc->getElementsByTagName('div') as $div) {
                            $class = $div->getAttribute('class');
                            if ($class) {
                                \Log::info('UDN div class', ['class' => $class]);
                            }
                        }
                        // 支援多個主內容 class
                        $mainDiv = null;
                        $possibleClasses = [
                            'article-content__editor',
                            'article-content__paragraph',
                        ];
                        foreach ($possibleClasses as $class) {
                            $mainDiv = $xpath->query('//div[contains(@class, "' . $class . '")]')->item(0);
                            if ($mainDiv) {
                                \Log::info('UDN 命中 class', ['class' => $class]);
                                break;
                            }
                        }
                        if (!$mainDiv) {
                            \Log::warning('UDN 找不到主內容區塊');
                        }
                        $udnText = [];
                        if ($mainDiv) {
                            foreach ($mainDiv->getElementsByTagName('p') as $p) {
                                $pHtml = trim($doc->saveHTML($p));
                                \Log::info('UDN <p> text', ['text' => $p->textContent, 'html' => $pHtml]);
                                $udnText[] = $pHtml;
                            }
                        }
                        $joined = implode("\n\n", array_filter($udnText));
                        \Log::info('UDN joined content', ['joined' => $joined, 'length' => mb_strlen(strip_tags($joined))]);
                        if (mb_strlen(strip_tags($joined)) > 10) {
                            $cleanContent = $joined;
                            \Log::info('UDN 內容擷取成功，長度: ' . mb_strlen($joined));
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
                        }
                    } catch (\Exception $e) {
                        \Log::error('UDN 擷取失敗', ['error' => $e->getMessage()]);
                    }
                }
                // 特殊處理：Yahoo 新聞網
                elseif (strpos($url, 'yahoo.com') !== false) {
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
                        'url' => $url,
                        'usedSelector' => $usedSelector,
                        'pCount' => count($yahooText),
                        'joinedLength' => mb_strlen($joined),
                        'joinedSample' => mb_substr($joined, 0, 300),
                        'yahooText' => array_slice($yahooText, 0, 3), // 只記錄前3個段落
                    ]);
                    
                    if (mb_strlen(trim($joined)) > 50) {
                        $cleanContent = $joined;
                        \Log::info('Yahoo 新聞網內容擷取成功', [
                            'taskId' => $this->taskId,
                            'contentLength' => mb_strlen($joined),
                            'selector' => $usedSelector,
                        ]);
                    } else {
                        \Log::warning('Yahoo 新聞網內容擷取失敗，嘗試其他方法', [
                            'taskId' => $this->taskId,
                            'joinedLength' => mb_strlen($joined),
                        ]);
                    }
                }

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

        // 若主文擷取成功且有原始網址，於主文最後加上資料來源
        if ($plainText && preg_match('/^https?:\/\//i', trim($this->content))) {
            $plainText .= "\n\n---\n資料來源：<a href='" . trim($this->content) . "' target='_blank' rel='noopener noreferrer'>" . trim($this->content) . "</a>";
        }

        // 若主文擷取失敗或內容過短，給予提示並結束
        if (!$plainText || $plainText === '內容抓取失敗。' || mb_strlen($plainText) < 100) {
            \Log::warning('AIScanFakeNewsJob failed', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'reason' => '主文擷取失敗或內容過短',
                'plainTextLength' => mb_strlen($plainText),
            ]);
            Cache::put("ai_scan_progress_{$this->taskId}", [
                'progress' => '主文擷取失敗，請嘗試複製主文內容貼上',
                'result' => null,
                'detectionData' => $this->getOldDetectionData(),
            ], 600);
            return;
        }

        // AI 偵測是否為新聞或文章
        // 只寫 progress，不寫 detectionData，等 AI 回應後再寫入
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 偵測內容類型',
            'result' => null,
            'detectionData' => $this->getOldDetectionData(),
        ], 600);
        
        // 初始化 Gemini 客戶端
        $gemini = resolve(GeminiClient::class);
        
        $newsDetectionPrompt = "你是一個內容判斷專家。請分析以下內容是否為新聞報導、文章、值得查證的內容，或是否有詐騙、詐騙徵兆。\n\n判斷標準：\n- 新聞：包含時事、事件報導、事實陳述、客觀資訊\n- 文章：評論、分析、觀點、政策討論、社會議題等\n- 值得查證：包含事實陳述、數據、觀點、爭議性內容等\n- 詐騙：內容涉及金錢詐騙、假冒官方、釣魚連結、投資詐騙、假冒親友、假中獎、假冒客服、要求個資、可疑連結、誇大不實、恐嚇威脅等\n- 非查證內容：純廣告、小說、詩歌、食譜、技術教學等\n\n請嚴格按照以下 JSON 格式回覆，不要添加任何其他文字：\n\n{\n  \"is_news\": true,\n  \"is_scam\": false,\n  \"reason\": \"這是新聞報導或值得查證的內容，因為...\"\n}\n\n或\n\n{\n  \"is_news\": false,\n  \"is_scam\": true,\n  \"reason\": \"這是詐騙內容，因為...\"\n}\n\n或\n\n{\n  \"is_news\": false,\n  \"is_scam\": false,\n  \"reason\": \"這不是新聞或詐騙，因為...\"\n}\n\n待判斷內容：\n" . $plainText;
        \Log::info('AIScanFakeNewsJob newsDetectionPrompt', [
            'ip' => $ip,
            'taskId' => $this->taskId,
            'newsDetectionPrompt' => $newsDetectionPrompt,
        ]);
        try {
            $newsDetectionResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($newsDetectionPrompt);
            $aiResponse = trim($newsDetectionResult->text());
            
                    // 嘗試解析 JSON 回應
        $detectionData = null;
        if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
            $jsonStr = $matches[0];
            $detectionData = json_decode($jsonStr, true);
        }
        
        // 如果 JSON 解析失敗，嘗試清理和修復
        if (!$detectionData || json_last_error() !== JSON_ERROR_NONE) {
            \Log::info('AIScanFakeNewsJob JSON cleaning attempt', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'originalResponse' => $aiResponse,
            ]);
            
            // 清理常見的 JSON 格式問題
            $cleanJson = $aiResponse;
            $cleanJson = str_replace(['true或是false', 'true或false', 'true/false'], 'true', $cleanJson);
            $cleanJson = str_replace(['false或是true', 'false或true', 'false/true'], 'false', $cleanJson);
            $cleanJson = preg_replace('/[^a-zA-Z0-9\{\}\[\]":,\s\.]/', '', $cleanJson);
            
            // 嘗試修復常見的 JSON 錯誤
            $cleanJson = preg_replace('/,\s*}/', '}', $cleanJson); // 移除尾隨逗號
            $cleanJson = preg_replace('/,\s*]/', ']', $cleanJson); // 移除陣列尾隨逗號
            
            if (preg_match('/\{.*\}/s', $cleanJson, $matches)) {
                $jsonStr = $matches[0];
                $detectionData = json_decode($jsonStr, true);
                
                \Log::info('AIScanFakeNewsJob JSON after cleaning', [
                    'ip' => $ip,
                    'taskId' => $this->taskId,
                    'cleanedJson' => $jsonStr,
                    'detectionData' => $detectionData,
                    'jsonError' => json_last_error_msg(),
                ]);
            }
        }
            
            // 如果 JSON 解析失敗，嘗試從文字中判斷
            if (!$detectionData) {
                // 清理 AI 回應，移除可能的格式問題
                $cleanResponse = str_replace(['true或是false', 'true或false', 'true/false'], '', $aiResponse);
                $cleanResponse = preg_replace('/[^a-zA-Z0-9\{\}\[\]":,\s]/', '', $cleanResponse);
                
                // 再次嘗試解析清理後的 JSON
                if (preg_match('/\{.*\}/s', $cleanResponse, $matches)) {
                    $jsonStr = $matches[0];
                    $detectionData = json_decode($jsonStr, true);
                }
                
                // 如果還是失敗，使用文字判斷
                if (!$detectionData) {
                    if (strpos(strtolower($aiResponse), 'false') !== false || 
                        strpos($aiResponse, '否') !== false || 
                        strpos($aiResponse, '不是') !== false ||
                        strpos($aiResponse, '非') !== false || 
                        strpos($aiResponse, '無關') !== false) {
                        $detectionData = ['is_news' => false, 'reason' => 'AI 判斷為非新聞內容'];
                    } else {
                        $detectionData = ['is_news' => true, 'reason' => 'AI 判斷為新聞內容'];
                    }
                }
            }
            
            // 檢查是否為新聞或文章或詐騙
        if (empty($detectionData['is_news']) && empty($detectionData['is_scam'])) {
            \Log::info('AIScanFakeNewsJob content not news or scam', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'aiResponse' => $aiResponse,
                'detectionData' => $detectionData,
            ]);
            Cache::put("ai_scan_progress_{$this->taskId}", [
                'progress' => '此內容非新聞或詐騙，請確認輸入',
                'result' => null,
                'error' => '此內容非新聞、文章或詐騙，請確認輸入',
                'detectionData' => $detectionData,
            ], 600);
            return;
        }
        // 新增：AI 偵測內容類型結束時，寫入 detectionData 供 progress API 用
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 偵測內容類型',
            'result' => null,
            'detectionData' => $detectionData,
        ], 600);
            
            \Log::info('AIScanFakeNewsJob content is news', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'detectionData' => $detectionData,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('AIScanFakeNewsJob news detection failed', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
            // 檢查是否為配額超限錯誤
            if (strpos($e->getMessage(), 'quota') !== false || 
                strpos($e->getMessage(), 'exceeded') !== false ||
                strpos($e->getMessage(), 'rate') !== false) {
                Cache::put("ai_scan_progress_{$this->taskId}", [
                    'progress' => 'AI 服務配額不足，請稍後再試',
                    'result' => null,
                    'error' => 'AI 服務配額不足，請稍後再試',
                    'detectionData' => ['is_news' => false, 'is_scam' => false, 'reason' => 'AI 服務配額不足'],
                ], 600);
                return;
            }
            // 其他 AI 請求失敗也寫入 detectionData，避免 null
            Cache::put("ai_scan_progress_{$this->taskId}", [
                'progress' => 'AI 偵測內容類型',
                'result' => null,
                'error' => 'AI 請求失敗，請稍後再試',
                'detectionData' => ['is_news' => false, 'is_scam' => false, 'reason' => 'AI 請求失敗'],
            ], 600);
            return;
        }

        // 2. AI 產生查證關鍵字並搜尋站內新聞
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 產生搜尋關鍵字',
            'result' => null,
            'detectionData' => $this->getOldDetectionData(),
        ], 600);
        $now = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
        $plainTextMarked = "【主文開始】\n" . $plainText . "\n【主文結束】";
        $promptKeyword = "你是一個新聞查證專家。請分析以下新聞內容，提取3-5個最重要的關鍵字用於查證。

請嚴格按照以下 JSON 格式回覆，不要添加任何其他文字：

{
  \"keywords\": [\"關鍵字1\", \"關鍵字2\", \"關鍵字3\", \"關鍵字4\", \"關鍵字5\"],
  \"search_phrase\": \"關鍵字1 關鍵字2 關鍵字3\"
}

要求：
- 關鍵字應該是名詞、人名、地名、事件名稱等具體實體
- 搜尋短語是關鍵字的組合，用於搜尋相關新聞
- 所有關鍵字必須是繁體中文

新聞內容：
" . $plainTextMarked;
        
        try {
            $resultKeyword = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($promptKeyword);
            $keywordResponse = trim($resultKeyword->text());
            
            // 嘗試解析 JSON 回應
            $keywordData = null;
            if (preg_match('/\{.*\}/s', $keywordResponse, $matches)) {
                $jsonStr = $matches[0];
                $keywordData = json_decode($jsonStr, true);
            }
            
            // 如果 JSON 解析失敗，使用傳統方式
            if (!$keywordData || !isset($keywordData['keywords'])) {
                $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $keywordResponse));
                $keywordsArr = array_filter(array_map('trim', explode(',', $keywords)));
                $searchKeyword = implode(' ', array_slice($keywordsArr, 0, 5));
            } else {
                $searchKeyword = $keywordData['search_phrase'] ?? implode(' ', $keywordData['keywords']);
            }
        } catch (\Exception $e) {
            \Log::error('AIScanFakeNewsJob keyword generation failed', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
            
            // 檢查是否為配額超限錯誤
            if (strpos($e->getMessage(), 'quota') !== false || 
                strpos($e->getMessage(), 'exceeded') !== false ||
                strpos($e->getMessage(), 'rate') !== false) {
                Cache::put("ai_scan_progress_{$this->taskId}", [
                    'progress' => 'AI 服務配額不足，請稍後再試',
                    'result' => null,
                    'error' => 'AI 服務配額不足，請稍後再試',
                ], 600);
                return;
            }
            
            // 如果關鍵字生成失敗，使用簡單的關鍵字提取
            $searchKeyword = implode(' ', array_slice(explode(' ', $plainText), 0, 5));
        }

        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '正在搜尋新聞資料',
            'result' => null,
            'detectionData' => $this->getOldDetectionData(),
        ], 600);
        sleep(1);
        // 1. 站內新聞搜尋
        $articles = Article::search($searchKeyword)->take(3)->get();
        $searchText = '';
        $externalUrls = [];
        foreach ($articles as $idx => $article) {
            // 如果是文章可信度分析，排除自己
            if ($this->articleId && $article->id == $this->articleId) {
                continue;
            }
            $searchText .= ($idx+1) . ". [站內] 標題：" . $article->title . "\n";
            $searchText .= "摘要：" . ($article->summary ?: mb_substr(strip_tags($article->content),0,100)) . "\n";
            $searchText .= "來源：" . ($article->source_url ?? '') . "\n---\n";
            if (!empty($article->source_url)) {
                $externalUrls[] = $article->source_url;
            }
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
                        if (!empty($article['url'])) {
                            $externalUrls[] = $article['url'];
                        }
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
                        if (!empty($article['source_url'])) {
                            $externalUrls[] = $article['source_url'];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('NewsData API 查詢失敗', [
                    'taskId' => $this->taskId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. Google Custom Search 或 Brave Search
        $googleSearchText = '';
        $useBraveSearch = false;
        
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
                    $googleSearchText .= "來源：" . $item['link'] ?? '' . " \n---\n";
                    if (!empty($item['link'])) {
                        $externalUrls[] = $item['link'];
                    }
                }
                
                \Log::info('Google Search API 查詢成功', [
                    'taskId' => $this->taskId,
                    'resultCount' => count($googleData['items']),
                    'searchKeyword' => $searchKeyword,
                ]);
            } else {
                \Log::warning('Google Search API 無搜尋結果', [
                    'taskId' => $this->taskId,
                    'searchKeyword' => $searchKeyword,
                    'googleData' => $googleData,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Google Search API 查詢失敗', [
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
            
            // 檢查是否為 429 錯誤（配額超限）
            $errorMessage = $e->getMessage();
            $isQuotaExceeded = strpos($errorMessage, '429') !== false || 
                              strpos($errorMessage, 'Quota exceeded') !== false ||
                              strpos($errorMessage, 'quota') !== false;
            
            if ($isQuotaExceeded) {
                $useBraveSearch = true;
                \Log::info('Google Search API 配額超限，切換到 Brave Search', [
                    'taskId' => $this->taskId,
                    'errorMessage' => $errorMessage,
                    'isQuotaExceeded' => $isQuotaExceeded,
                ]);
            } else {
                \Log::warning('Google Search API 其他錯誤，不切換到 Brave Search', [
                    'taskId' => $this->taskId,
                    'errorMessage' => $errorMessage,
                ]);
            }
        }
        
        // 如果 Google Search 失敗且是 429 錯誤，使用 Brave Search
        if ($useBraveSearch) {
            try {
                $braveSearchService = new BraveSearchService();
                
                \Log::info('Brave Search 服務配置檢查', [
                    'taskId' => $this->taskId,
                    'config' => $braveSearchService->getConfig(),
                ]);
                
                if ($braveSearchService->isConfigured()) {
                    \Log::info('使用 Brave Search API', [
                        'taskId' => $this->taskId,
                        'searchKeyword' => $searchKeyword,
                    ]);
                    
                    $braveResults = $braveSearchService->webSearch($searchKeyword);
                    
                    if (!empty($braveResults)) {
                        foreach ($braveResults as $idx => $item) {
                            $googleSearchText .= ($idx+1) . ". [Brave] 標題：" . $item['title'] . "\n";
                            $googleSearchText .= "摘要：" . $item['description'] . "\n";
                            $googleSearchText .= "來源：" . $item['url'] . " \n---\n";
                            if (!empty($item['url'])) {
                                $externalUrls[] = $item['url'];
                            }
                        }
                        
                        \Log::info('Brave Search API 查詢成功', [
                            'taskId' => $this->taskId,
                            'resultCount' => count($braveResults),
                        ]);
                    } else {
                        \Log::warning('Brave Search API 無搜尋結果', [
                            'taskId' => $this->taskId,
                        ]);
                    }
                } else {
                    \Log::warning('Brave Search API 未配置，請在 config/bravesearch.php 中設定 API Key', [
                        'taskId' => $this->taskId,
                        'configFile' => config_path('bravesearch.php'),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Brave Search API 查詢失敗', [
                    'taskId' => $this->taskId,
                    'error' => $e->getMessage(),
                    'errorCode' => $e->getCode(),
                    'errorFile' => $e->getFile(),
                    'errorLine' => $e->getLine(),
                ]);
            }
        }
        $searchText .= $googleSearchText;
        if (!$searchText) {
            $searchText = '（查無相關新聞）';
        }

        // 新增：AI 摘要每個外部網站內容
        $externalSummaries = [];
        foreach ($externalUrls as $url) {
            try {
                // 1. 爬蟲抓主文
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
        // 將摘要加入查證資料
        if (!empty($externalSummaries)) {
            $searchText .= "\n\n【AI 網頁摘要】\n";
            foreach ($externalSummaries as $url => $summary) {
                $searchText .= "來源：$url\n摘要：$summary\n---\n";
            }
        }

        // 3. AI 綜合查證
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 綜合查證中',
            'result' => null,
            'detectionData' => $this->getOldDetectionData(),
        ], 600);
        $nowTime = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
        $plainTextMarked = "【主文開始】\n" . $plainText . "\n【主文結束】";

        // 判斷是否為詐騙內容
        $isScam = false;
        if (isset($detectionData['is_scam']) && $detectionData['is_scam'] === true) {
            $isScam = true;
        }

        if ($isScam) {
            $prompt = "你是一位詐騙偵測與防詐專家。請根據查證資料，詳細分析用戶輸入內容的詐騙手法、徵兆、危險性，並給出具體防詐建議。\n\n請嚴格按照以下 JSON 格式回覆，不要添加任何其他文字：\n\n{\n  \"analysis\": \"詳細的詐騙手法與徵兆分析\",\n  \"scam_risk_level\": \"高/中/低\",\n  \"recommendation\": \"防詐建議\",\n  \"sources\": [\"查證來源1\", \"查證來源2\"]\n}\n\n要求：\n- analysis: 詳細說明詐騙手法、徵兆、危險性\n- scam_risk_level: 高/中/低\n- recommendation: 給讀者的防詐建議\n- sources: 列出所有查證時參考的來源\n\n查證時間：{$nowTime}\n\n查證資料：\n{$searchText}\n\n待查證內容：\n{$plainTextMarked}\n\n資料來源：" . (preg_match('/^https?:\/\//i', trim($this->content)) ? $this->content : '用戶貼上主文') . "\n查證關鍵字：{$searchKeyword}";
        } else {
            $prompt = "你是一個新聞可信度分析專家。請根據提供的查證資料，分析用戶輸入的新聞內容的可信度。\n\n請嚴格按照以下 JSON 格式回覆，不要添加任何其他文字：\n\n{\n  \"analysis\": \"詳細的查證過程和可信度分析理由\",\n  \"credibility_score\": 85,\n  \"recommendation\": \"對讀者的建議和提醒\",\n  \"sources\": [\"查證來源1\", \"查證來源2\", \"查證來源3\"]\n}\n\n要求：\n- credibility_score: 0-100 的整數，代表可信度百分比\n- analysis: 詳細說明查證過程和判斷理由\n- recommendation: 給讀者的具體建議\n- sources: 列出所有查證時參考的來源\n\n查證時間：{$nowTime}\n\n查證資料：\n{$searchText}\n\n待查證新聞：\n{$plainTextMarked}\n\n資料來源：" . (preg_match('/^https?:\/\//i', trim($this->content)) ? $this->content : '用戶貼上主文') . "\n查證關鍵字：{$searchKeyword}";
        }
        
        \Log::info('AIScanFakeNewsJob analysis prompt', [
            'ip' => $ip,
            'taskId' => $this->taskId,
            'prompt' => $prompt,
        ]);
        
        try {
            $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
            $aiResponse = trim($result->text());
            
            \Log::info('AIScanFakeNewsJob analysis response', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'aiResponse' => $aiResponse,
            ]);
            
            // 嘗試解析 JSON 回應
            $analysisData = null;
            
            // 嘗試多種方式提取 JSON
            $jsonStr = null;
            
            // 方法1: 尋找完整的 JSON 物件
            if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $aiResponse, $matches)) {
                $jsonStr = $matches[0];
            }
            // 方法2: 簡單的大括號匹配
            elseif (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
                $jsonStr = $matches[0];
            }
            
            if ($jsonStr) {
                $analysisData = json_decode($jsonStr, true);
                
                \Log::info('AIScanFakeNewsJob JSON parsing', [
                    'ip' => $ip,
                    'taskId' => $this->taskId,
                    'jsonStr' => $jsonStr,
                    'analysisData' => $analysisData,
                    'jsonError' => json_last_error_msg(),
                ]);
            }
            
            // 如果 JSON 解析失敗，使用原始回應
            if (!$analysisData || json_last_error() !== JSON_ERROR_NONE) {
                \Log::warning('AIScanFakeNewsJob JSON parsing failed, using original response', [
                    'ip' => $ip,
                    'taskId' => $this->taskId,
                    'aiResponse' => $aiResponse,
                    'jsonError' => json_last_error_msg(),
                ]);
                
                // 如果 JSON 解析失敗，回退到原本的格式
                $fallbackPrompt = "請參考下列新聞資料，針對用戶輸入的內容（主文已用【主文開始】與【主文結束】標記）進行查證，並以繁體中文簡要說明查證過程與理由，最後請獨立一行以【可信度：xx%】格式標示可信度，再給出建議。請將主文原文用【主文開始】與【主文結束】標記包住。所有網址連結結束處請加上一個空格。請在回應最後以**【查證出處】**區塊列出所有引用的網站、新聞來源或資料連結。\n\n【查證時間：{$nowTime}】\n\n【新聞資料】\n" . $searchText . "\n【用戶輸入】\n" . $plainTextMarked . "\n\n---\n資料來源：" . (preg_match('/^https?:\/\//i', trim($this->content)) ? $this->content : '用戶貼上主文') . "\n查證關鍵字：" . $searchKeyword;
                
                $fallbackResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($fallbackPrompt);
                $aiText = trim($fallbackResult->text());
                
                \Log::info('AIScanFakeNewsJob fallback result', [
                    'ip' => $ip,
                    'taskId' => $this->taskId,
                    'fallbackText' => $aiText,
                ]);
            } else {
                // 格式化為原本的格式
                $aiText = $analysisData['analysis'] . "\n\n【可信度：" . $analysisData['credibility_score'] . "%】\n\n" . $analysisData['recommendation'] . "\n\n【查證出處】\n" . implode("\n", $analysisData['sources']);
                
                \Log::info('AIScanFakeNewsJob formatted result', [
                    'ip' => $ip,
                    'taskId' => $this->taskId,
                    'formattedText' => $aiText,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('AIScanFakeNewsJob analysis failed', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
            
            // 檢查是否為配額超限錯誤
            if (strpos($e->getMessage(), 'quota') !== false || 
                strpos($e->getMessage(), 'exceeded') !== false ||
                strpos($e->getMessage(), 'rate') !== false) {
                Cache::put("ai_scan_progress_{$this->taskId}", [
                    'progress' => 'AI 服務配額不足，請稍後再試',
                    'result' => null,
                    'error' => 'AI 服務配額不足，請稍後再試',
                ], 600);
                return;
            }
            
            // 如果 AI 綜合查證失敗，回退到原本的格式
            $fallbackPrompt = "請參考下列新聞資料，針對用戶輸入的內容（主文已用【主文開始】與【主文結束】標記）進行查證，並以繁體中文簡要說明查證過程與理由，最後請獨立一行以【可信度：xx%】格式標示可信度，再給出建議。請將主文原文用【主文開始】與【主文結束】標記包住。所有網址連結結束處請加上一個空格。請在回應最後以**【查證出處】**區塊列出所有引用的網站、新聞來源或資料連結。\n\n【查證時間：{$nowTime}】\n\n【新聞資料】\n" . $searchText . "\n【用戶輸入】\n" . $plainTextMarked . "\n\n---\n資料來源：" . (preg_match('/^https?:\/\//i', trim($this->content)) ? $this->content : '用戶貼上主文') . "\n查證關鍵字：" . $searchKeyword;
            
            $fallbackResult = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($fallbackPrompt);
            $aiText = trim($fallbackResult->text());
            
            \Log::info('AIScanFakeNewsJob fallback result', [
                'ip' => $ip,
                'taskId' => $this->taskId,
                'fallbackText' => $aiText,
            ]);
        }

        // 儲存結果到資料庫
        \Log::info('AIScanFakeNewsJob 準備儲存結果', [
            'ip' => $ip,
            'taskId' => $this->taskId,
            'aiTextLength' => mb_strlen($aiText),
            'searchKeyword' => $searchKeyword,
        ]);
        
        $this->saveScanResult($aiText, $searchKeyword, $externalSummaries ?? []);

        // 完成
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '完成',
            'result' => $aiText,
            'detectionData' => $this->getOldDetectionData(),
        ], 600);
        
        \Log::info('AIScanFakeNewsJob 完成', [
            'ip' => $ip,
            'taskId' => $this->taskId,
        ]);

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

    /**
     * 儲存掃描結果到資料庫
     */
    private function saveScanResult(string $analysisResult, string $searchKeyword, array $externalSummaries = [])
    {
        try {
            $credibilityScore = $this->extractCredibilityScore($analysisResult);
            
            // 提取查證來源
            $verificationSources = [];
            if (preg_match('/【查證出處】([\s\S]*)$/', $analysisResult, $matches)) {
                $sources = trim($matches[1]);
                $verificationSources = array_filter(array_map('trim', explode("\n", $sources)));
            }

            // 獲取查證的內文內容
            $verifiedContent = $this->getVerifiedContent();
            
            \Log::info('AIScanFakeNewsJob 獲取查證內文', [
                'taskId' => $this->taskId,
                'verifiedContentLength' => mb_strlen($verifiedContent),
                'verifiedContentSample' => mb_substr($verifiedContent, 0, 100),
            ]);

            AiScanResult::create([
                'task_id' => $this->taskId,
                'original_content' => $this->content,
                'verified_content' => $verifiedContent,
                'analysis_result' => $analysisResult,
                'credibility_score' => $credibilityScore,
                'client_ip' => $this->clientIp,
                'user_agent' => 'AI Scan Job',
                'search_keywords' => [$searchKeyword],
                'verification_sources' => $verificationSources,
                'completed_at' => now(),
                'external_summaries' => $externalSummaries,
            ]);

            \Log::info('AI 掃描結果已儲存到資料庫', [
                'taskId' => $this->taskId,
                'credibilityScore' => $credibilityScore,
                'verifiedContentLength' => mb_strlen($verifiedContent),
            ]);

        } catch (\Exception $e) {
            \Log::error('儲存 AI 掃描結果失敗', [
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 獲取查證的內文內容
     */
    private function getVerifiedContent(): string
    {
        // 如果輸入是 URL，返回抓取的內容
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
                
                // 處理編碼問題
                if (strpos($url, 'yahoo.com') !== false) {
                    $encoding = mb_detect_encoding($html, ['UTF-8', 'BIG5', 'GBK', 'GB2312', 'ISO-8859-1'], true);
                    if ($encoding && strtoupper($encoding) !== 'UTF-8') {
                        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
                    }
                }
                
                $doc = new \DOMDocument();
                @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                $xpath = new \DOMXPath($doc);
                
                $cleanContent = null;
                
                // 特殊處理：SETN 新聞網
                if (strpos($url, 'https://www.setn.com/News.aspx?NewsID=') !== false) {
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
                    }
                }
                // 特殊處理：UDN 新聞網
                elseif (strpos($url, 'udn.com/news') !== false) {
                    // 自動轉換 UDN AMP 版網址
                    if (preg_match('#^https://udn.com/news/story/(\d+)/(\d+)$#', $url, $matches)) {
                        $url = "https://udn.com/news/amp/story/{$matches[1]}/{$matches[2]}/";
                        \Log::info('自動轉換 UDN AMP 版網址', ['amp_url' => $url]);
                    }
                    $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
                    try {
                        $res = $client->get($url, [
                            'headers' => [
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                                'Cookie' => 'udn_papercookie=1;',
                                'Accept-Language' => 'zh-TW,zh;q=0.9',
                            ],
                        ]);
                        $html = (string) $res->getBody();
                        \Log::info('UDN raw HTML', ['html_sample' => mb_substr($html, 0, 1000)]);
                        $doc = new \DOMDocument();
                        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                        $xpath = new \DOMXPath($doc);
                        // log 所有 div 的 class
                        foreach ($doc->getElementsByTagName('div') as $div) {
                            $class = $div->getAttribute('class');
                            if ($class) {
                                \Log::info('UDN div class', ['class' => $class]);
                            }
                        }
                        $editorDiv = $xpath->query('//div[contains(@class, "article-content__editor")]')->item(0);
                        if (!$editorDiv) {
                            \Log::warning('UDN 找不到 article-content__editor');
                        }
                        $udnText = [];
                        if ($editorDiv) {
                            \Log::info('UDN editorDiv HTML', ['html' => $doc->saveHTML($editorDiv)]);
                            foreach ($editorDiv->getElementsByTagName('p') as $p) {
                                $pHtml = trim($doc->saveHTML($p));
                                \Log::info('UDN <p> text', ['text' => $p->textContent, 'html' => $pHtml]);
                                $udnText[] = $pHtml;
                            }
                        }
                        $joined = implode("\n\n", array_filter($udnText));
                        \Log::info('UDN joined content', ['joined' => $joined, 'length' => mb_strlen(strip_tags($joined))]);
                        if (mb_strlen(strip_tags($joined)) > 10) { // 放寬長度判斷
                            $cleanContent = $joined;
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
                        }
                    } catch (\Exception $e) {
                        \Log::error('UDN 擷取失敗', ['error' => $e->getMessage()]);
                    }
                }
                // 特殊處理：Yahoo 新聞網
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
                                break;
                            }
                        }
                    }
                    
                    $joined = implode("\n\n", array_filter($yahooText));
                    if (mb_strlen(trim($joined)) > 50) {
                        $cleanContent = $joined;
                    }
                }
                // 特殊處理：LINE TODAY 新聞
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
                        $cleanContent = strip_tags($rawContent);
                    } else {
                        // 使用 Readability
                        $config = new Configuration();
                        $readability = new Readability($config);
                        $readability->parse($html, $url);
                        $readContent = $readability->getContent();
                        if ($readContent) {
                            $cleanContent = strip_tags($readContent);
                        }
                    }
                }
                
                if (empty($cleanContent) || trim(strip_tags($cleanContent)) === '') {
                    return '無法提取網頁內容';
                }
                
                return trim(strip_tags($cleanContent));
                
            } catch (\Exception $e) {
                return '無法抓取內容：' . $e->getMessage();
            }
        }
        
        // 如果輸入是文字，直接返回
        return trim($this->content);
    }
} 