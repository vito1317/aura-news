<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Gemini\Client as GeminiClient;
use App\Jobs\AIScanFakeNewsJob;

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
                : $request->ip();
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
            : $request->ip();
        Cache::put("ai_scan_progress_{$taskId}", [
            'progress' => '已接收請求',
            'result' => null,
        ], 600);
        Cache::put("ai_scan_content_{$taskId}", $content, 600);
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
        // 新增：若進度為「此內容非新聞，請確認輸入」，回傳 error 欄位
        if (isset($data['progress']) && $data['progress'] === '此內容非新聞，請確認輸入') {
            return response()->json([
                'progress' => $data['progress'],
                'error' => '此內容非新聞，請確認輸入',
            ]);
        }
        return response()->json($data);
    }
} 