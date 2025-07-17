<?php

namespace App\Observers;

use App\Models\Article;
use Gemini\Client as GeminiClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\TransferStats;
use Gemini\Data\Content;
use Gemini\Enums\Role; 
use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

class ArticleObserver
{
    public function created(Article $article): void
    {
        try {
            $guzzle = new GuzzleClient();
            $response = $guzzle->request('GET', $article->source_url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ]
            ]);
            $html = (string) $response->getBody();

            if (preg_match('/<meta\\s+(?:property|name)="og:image"\\s+content="([^"]+)"/i', $html, $matches)) {
                $article->image_url = $matches[1];
                if ($article->isDirty()) {
                    $article->saveQuietly();
                }
            }
        } catch (\Exception $e) {
            \Log::error('Observer 任務失敗 for article ID: ' . $article->id . ' - ' . $e->getMessage());
            \Log::error('HTML content: ' . substr($html ?? '', 0, 500)); // 只記錄前 500 字
        }
    }
}