<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NewsDataApiService
{
    protected $apiKey;
    protected $baseUrl = 'https://newsdata.io/api/1';

    public function __construct()
    {
        $this->apiKey = config('services.newsdata.key');
    }

    public function searchArticles($query, $language = 'zh', $pageSize = 20)
    {
        if (!$this->apiKey) {
            Log::error('NewsData API Key 未設定');
            return null;
        }

        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/latest', [
                'apikey' => $this->apiKey,
                'q' => $query,
                'language' => $language,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'success' && isset($data['results'])) {
                    return $this->transformArticles($data['results']);
                }
            }

            Log::error('NewsData API 請求失敗', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('NewsData API 請求異常', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            return null;
        }
    }

    protected function transformArticles($articles)
    {
        return collect($articles)->map(function ($article) {
            return [
                'source_url' => $article['link'] ?? null,
                'title' => $article['title'] ?? '',
                'content' => $article['content'] ?? $article['description'] ?? '',
                'image_url' => $article['image_url'] ?? null,
                'summary' => $article['description'] ?? null,
                'author' => $this->extractAuthor($article),
                'published_at' => $this->parseDate($article['pubDate'] ?? null),
                'source_name' => $article['source_name'] ?? '未知來源',
                'keywords' => $article['keywords'] ?? [],
                'category' => $this->mapCategory($article['category'] ?? []),
                'country' => $article['country'] ?? [],
                'language' => $article['language'] ?? 'zh',
            ];
        })->filter(function ($article) {
            // 過濾無效文章
            return !empty($article['source_url']) && 
                   !empty($article['title']) && 
                   $this->isValidImageUrl($article['image_url']);
        })->values();
    }

    protected function extractAuthor($article)
    {
        if (isset($article['creator']) && is_array($article['creator'])) {
            return implode(', ', array_filter($article['creator']));
        }
        
        if (isset($article['creator']) && is_string($article['creator'])) {
            return $article['creator'];
        }

        return $article['source_name'] ?? '未知來源';
    }

    protected function parseDate($dateString)
    {
        if (!$dateString) {
            return now();
        }

        try {
            return Carbon::parse($dateString)->addHours(8)->setTimezone('Asia/Taipei');
        } catch (\Exception $e) {
            Log::warning('無法解析日期', ['date' => $dateString]);
            return now();
        }
    }

    protected function mapCategory($categories)
    {
        if (empty($categories)) {
            return ['general'];
        }

        $categoryMap = [
            'technology' => '科技',
            'politics' => '政治',
            'business' => '商業',
            'sports' => '體育',
            'entertainment' => '娛樂',
            'health' => '健康',
            'science' => '科學',
            'top' => '頭條',
        ];

        $mappedCategories = [];
        foreach ($categories as $category) {
            $mappedCategories[] = $categoryMap[$category] ?? $category;
        }

        return $mappedCategories;
    }

    protected function isValidImageUrl($imageUrl)
    {
        if (!$imageUrl || !preg_match('/^https?:\/\//', $imageUrl)) {
            return false;
        }

        // 過濾一些常見的無效圖片 URL
        $invalidPatterns = [
            '/consent\.yahoo\.com/',
            '/placeholder\.com/',
            '/default\.jpg/',
            '/no-image/',
        ];

        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $imageUrl)) {
                return false;
            }
        }

        return true;
    }
} 