<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Gemini\Client as GeminiClient;
use App\Jobs\AIScanFakeNewsJob;
use App\Models\AiScanResult;

class AIScanFakeNewsController extends Controller
{
    public function start(Request $request)
    {
        try {
            $data = $request->validate([
                'content' => 'required|string|min:10',
            ]);
        } catch (\Exception $e) {
            $clientIp = $request->header('X-Forwarded-For') 
                ? explode(',', $request->header('X-Forwarded-For'))[0] 
                : $request->header('X-Forwarded-For');
            \Log::warning('AIScanFakeNewsController validate failed', [
                'ip' => $clientIp,
                'reason' => $e->getMessage(),
            ]);
            return response()->json(['error' => '請輸入正確的新聞內容或網址'], 400);
        }
        $content = $data['content'];
        $taskId = (string) Str::uuid();
        $clientIp = $request->header('X-Forwarded-For') 
            ? explode(',', $request->header('X-Forwarded-For'))[0] 
            : $request->header('X-Forwarded-For');
        
        \Log::info('AIScanFakeNewsController 收到請求', [
            'taskId' => $taskId,
            'ip' => $clientIp,
            'contentLength' => mb_strlen($content),
            'contentSample' => mb_substr($content, 0, 200),
        ]);
        Cache::put("ai_scan_progress_{$taskId}", [
            'progress' => '已接收請求',
            'result' => null,
        ], 600);
        Cache::put("ai_scan_content_{$taskId}", $content, 600);
        Cache::put("ai_scan_created_{$taskId}", time(), 600);
        try {
            AIScanFakeNewsJob::dispatch($taskId, $content, $clientIp, null);
        } catch (\Exception $e) {
            \Log::error('AIScanFakeNewsController job dispatch failed', [
                'ip' => $clientIp,
                'taskId' => $taskId,
                'reason' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'AI 查證服務啟動失敗，請稍後再試'], 500);
        }
        return response()->json(['taskId' => $taskId]);
    }

    public function progress($taskId)
    {
        $data = \Cache::get("ai_scan_progress_{$taskId}");
        if (!$data) {
            return response()->json(['progress' => 'not_found'], 404);
        }
        
        // 檢查是否有錯誤訊息
        if (isset($data['error'])) {
            return response()->json([
                'progress' => $data['progress'],
                'error' => $data['error'],
            ]);
        }
        
        if (isset($data['progress']) && $data['progress'] === '此內容非新聞，請確認輸入') {
            return response()->json([
                'progress' => $data['progress'],
                'error' => '此內容非新聞或文章，請確認輸入',
            ]);
        }

        // 新增：若已偵測到 is_scam，回傳 scam 標記
        $scam = false;
        if (isset($data['detectionData']['is_scam']) && $data['detectionData']['is_scam'] === true) {
            $scam = true;
        }
        $resp = $data;
        if ($scam) {
            $resp['is_scam'] = true;
        }
        // 保證 detectionData 欄位永遠存在
        if (!array_key_exists('detectionData', $resp)) {
            $resp['detectionData'] = null;
        }
        // 檢查是否排隊中（停留在「已接收請求」狀態超過 30 秒）
        if (isset($data['progress']) && $data['progress'] === '已接收請求') {
            $createdAt = \Cache::get("ai_scan_created_{$taskId}");
            if ($createdAt && (time() - $createdAt) > 30) {
                $resp['progress'] = '排隊中，請稍後';
                $resp['isQueued'] = true;
            }
        }
        return response()->json($resp);
    }

    /**
     * 根據 task_id 獲取掃描結果
     */
    public function getResult($taskId)
    {
        try {
            $result = AiScanResult::where('task_id', $taskId)->first();
            
            if (!$result) {
                return response()->json([
                    'error' => '找不到掃描結果',
                    'message' => '此 QR Code 對應的掃描結果不存在或已過期'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'task_id' => $result->task_id,
                    'original_content' => $result->original_content,
                    'verified_content' => $result->verified_content,
                    'analysis_result' => $result->analysis_result,
                    'credibility_score' => $result->credibility_score,
                    'analysis_content' => $result->getAnalysisContent(),
                    'verification_sources' => $result->extractVerificationSources(),
                    'search_keywords' => $result->search_keywords,
                    'completed_at' => $result->completed_at,
                    'created_at' => $result->created_at,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('獲取掃描結果失敗', [
                'taskId' => $taskId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => '獲取掃描結果失敗',
                'message' => '系統錯誤，請稍後再試'
            ], 500);
        }
    }

    /**
     * 取得 AI 假新聞查證使用次數（今日、總計）
     */
    public function usageCount()
    {
        $aiTotal = \App\Models\AiScanResult::count();
        $aiToday = \App\Models\AiScanResult::whereDate('created_at', now()->toDateString())->count();
        $articleTotal = \App\Models\Article::count();
        $articleToday = \App\Models\Article::whereDate('created_at', now()->toDateString())->count();
        return response()->json([
            'ai_total' => $aiTotal,
            'ai_today' => $aiToday,
            'article_total' => $articleTotal,
            'article_today' => $articleToday,
            'total' => $aiTotal + $articleTotal,
            'today' => $aiToday + $articleToday,
        ]);
    }
} 