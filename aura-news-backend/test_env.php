<?php

// 載入 Laravel 環境
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 環境變數檢查 ===\n";
echo "APP_ENV: " . env('APP_ENV', '未設定') . "\n";
echo "BRAVE_SEARCH_API_KEY: " . (env('BRAVE_SEARCH_API_KEY') ? '已設定 (' . substr(env('BRAVE_SEARCH_API_KEY'), 0, 10) . '...)' : '未設定') . "\n";
echo "GOOGLE_SEARCH_API_KEY: " . (env('GOOGLE_SEARCH_API_KEY') ? '已設定' : '未設定') . "\n";
echo "NEWS_API_KEY: " . (env('NEWS_API_KEY') ? '已設定' : '未設定') . "\n";
echo "NEWSDATA_API_KEY: " . (env('NEWSDATA_API_KEY') ? '已設定' : '未設定') . "\n";

echo "\n=== Brave Search 配置檢查 ===\n";
try {
    $braveConfig = config('bravesearch');
    echo "配置文件載入: " . ($braveConfig ? '成功' : '失敗') . "\n";
    echo "API Key 設定: " . ($braveConfig['api_key'] ? '已設定' : '未設定') . "\n";
    echo "Base URL: " . $braveConfig['base_url'] . "\n";
    echo "Timeout: " . $braveConfig['timeout'] . " 秒\n";
    echo "Max Results: " . $braveConfig['max_results'] . "\n";
    echo "Enable Fallback: " . ($braveConfig['enable_fallback'] ? '是' : '否') . "\n";
} catch (Exception $e) {
    echo "配置檢查失敗: " . $e->getMessage() . "\n";
}

echo "\n=== Brave Search 服務測試 ===\n";
try {
    $braveService = new \App\Services\BraveSearchService();
    $serviceConfig = $braveService->getConfig();
    echo "服務配置: " . json_encode($serviceConfig, JSON_PRETTY_PRINT) . "\n";
    echo "服務可用: " . ($braveService->isConfigured() ? '是' : '否') . "\n";
} catch (Exception $e) {
    echo "服務測試失敗: " . $e->getMessage() . "\n";
}

echo "\n=== 建議 ===\n";
echo "如果 Brave Search API 未配置，請在 .env 文件中添加：\n";
echo "BRAVE_SEARCH_API_KEY=your_brave_search_api_key_here\n";
echo "\n獲取 API Key 的步驟：\n";
echo "1. 前往 https://api-dashboard.search.brave.com/\n";
echo "2. 註冊並獲取 API Key\n";
echo "3. 將 API Key 添加到 .env 文件中\n";
echo "\n配置完成後，系統會自動使用 Brave Search 作為 Google Search 的備用方案。\n"; 