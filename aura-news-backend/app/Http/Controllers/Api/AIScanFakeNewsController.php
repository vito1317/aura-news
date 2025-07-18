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
        $data = $request->validate([
            'content' => 'required|string|min:10',
        ]);
        $content = $data['content'];
        $taskId = (string) Str::uuid();
        Cache::put("ai_scan_progress_{$taskId}", [
            'progress' => '已接收請求',
            'result' => null,
        ], 600);
        Cache::put("ai_scan_content_{$taskId}", $content, 600);
        // Dispatch queue job
        AIScanFakeNewsJob::dispatch($taskId, $content);
        return response()->json(['taskId' => $taskId]);
    }

    public function progress($taskId)
    {
        $data = \Cache::get("ai_scan_progress_{$taskId}");
        if (!$data) {
            return response()->json(['progress' => 'not_found'], 404);
        }
        return response()->json($data);
    }
} 