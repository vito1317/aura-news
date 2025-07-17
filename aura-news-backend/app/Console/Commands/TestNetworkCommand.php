<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class TestNetworkCommand extends Command
{
    protected $signature = 'app:test-network';
    protected $description = '一個用來直接測試 Guzzle 網路連線的指令';

    public function handle()
    {
        $this->info("正在嘗試使用 Guzzle 連線到 https://www.google.com ...");
        
        try {
            $client = new Client([
                'timeout' => 10,
                'verify' => false, // 暫時忽略 SSL 驗證
            ]);

            $response = $client->request('GET', 'https://www.google.com');

            $statusCode = $response->getStatusCode();
            $contentLength = $response->getHeaderLine('Content-Length');

            $this->info("------ 測試成功！------");
            $this->info("狀態碼 (Status Code): " . $statusCode);
            $this->info("內容長度 (Content-Length): " . $contentLength);
            $this->line("結論：您的伺服器 CLI 環境可以正常發起對外 HTTP 請求。");
            return 0;

        } catch (\Exception $e) {
            $this->error("------ 測試失敗！------");
            $this->error("錯誤類型: " . get_class($e));
            $this->error("錯誤訊息: " . $e->getMessage());
            $this->line("結論：您的伺服器 CLI 環境在對外連線時遇到了障礙。");
            return 1;
        }
    }
}