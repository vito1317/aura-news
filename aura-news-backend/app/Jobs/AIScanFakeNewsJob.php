<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Gemini\Client as GeminiClient;

class AIScanFakeNewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $taskId;
    protected $content;

    public function __construct($taskId, $content)
    {
        $this->taskId = $taskId;
        $this->content = $content;
    }

    public function handle()
    {
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '正在查看新聞',
            'result' => null,
        ], 600);
        sleep(1);
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '正在上網搜尋資料',
            'result' => null,
        ], 600);
        sleep(2);
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => 'AI 分析中',
            'result' => null,
        ], 600);
        $gemini = resolve(GeminiClient::class);
        $now = now()->setTimezone('Asia/Taipei')->format('Y-m-d H:i');
        $prompt = "現在時間為 {$now}（UTC+8）。請先上網蒐集資料查證以下新聞內容的真實性，並根據查證結果判斷是否有假新聞、誤導、或不實資訊。請以繁體中文簡要說明查證過程與理由，最後給出可信度百分比與建議：\n\n" . $this->content;
        $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
        $aiText = $result->text();
        Cache::put("ai_scan_progress_{$this->taskId}", [
            'progress' => '完成',
            'result' => $aiText,
        ], 600);
    }
} 