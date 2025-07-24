<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;

class FillArticleKeywords extends Command
{
    protected $signature = 'app:fill-keywords';
    protected $description = '補齊未產生關鍵字的文章';

    public function handle()
    {
        $articles = Article::whereNull('keywords')->orWhere('keywords', '')->get();
        $this->info('共需補齊 ' . $articles->count() . ' 篇文章關鍵字');
        $count = 0;
        foreach ($articles as $article) {
            try {
                $gemini = resolve(\Gemini\Client::class);
                $prompt = "請根據以下新聞內容，產生3~5個適合用於分類與推薦的繁體中文關鍵字或短語，僅回傳關鍵字本身，用逗號分隔：\n\n" . strip_tags($article->content);
                $result = $gemini->generativeModel('gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);
                $keywords = trim(str_replace(["\n", "。", "，"], [',', '', ','], $result->text()));
                $article->keywords = $keywords;
                $article->save();
                $this->info('已補齊: ' . $article->id . ' - ' . mb_substr($article->title, 0, 30) . '... => ' . $keywords);
                $count++;
            } catch (\Exception $e) {
                $this->error('失敗: ' . $article->id . ' - ' . $e->getMessage());
            }
        }
        $this->info('補齊完成，共處理 ' . $count . ' 篇文章');
        return 0;
    }
} 