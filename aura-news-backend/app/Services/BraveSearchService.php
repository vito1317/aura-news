<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BraveSearchService
{
    protected $client;
    protected $config;

    public function __construct()
    {
        $this->config = config('bravesearch');
        $this->client = new Client([
            'timeout' => $this->config['timeout'],
            'verify' => false,
        ]);
    }

    /**
     * 執行網頁搜尋
     */
    public function webSearch(string $query, int $count = null): array
    {
        $count = $count ?? $this->config['max_results'];
        
        try {
            $response = $this->client->get($this->config['base_url'] . $this->config['endpoints']['web_search'], [
                'query' => [
                    'q' => $query,
                ],
                'headers' => array_merge($this->config['default_headers'], [
                    'X-Subscription-Token' => $this->config['api_key'],
                ]),
            ]);

            $data = json_decode($response->getBody(), true);
            
            Log::info('Brave Search API 查詢成功', [
                'query' => $query,
                'responseStatus' => $response->getStatusCode(),
                'resultCount' => isset($data['web']['results']) ? count($data['web']['results']) : 0,
            ]);

            return $this->formatResults($data, $count);
            
        } catch (\Exception $e) {
            Log::error('Brave Search API 查詢失敗', [
                'query' => $query,
                'error' => $e->getMessage(),
                'errorCode' => $e->getCode(),
            ]);
            
            return [];
        }
    }

    /**
     * 格式化搜尋結果
     */
    protected function formatResults(array $data, int $count): array
    {
        $results = [];
        
        if (!empty($data['web']['results'])) {
            $limitedResults = array_slice($data['web']['results'], 0, $count);
            
            foreach ($limitedResults as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? '',
                    'url' => $item['url'] ?? '',
                    'source' => 'Brave',
                ];
            }
        }
        
        return $results;
    }

    /**
     * 檢查 API Key 是否設定
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']);
    }

    /**
     * 獲取配置信息
     */
    public function getConfig(): array
    {
        return [
            'api_key_set' => $this->isConfigured(),
            'api_key_length' => $this->isConfigured() ? strlen($this->config['api_key']) : 0,
            'base_url' => $this->config['base_url'],
            'timeout' => $this->config['timeout'],
            'max_results' => $this->config['max_results'],
        ];
    }
} 