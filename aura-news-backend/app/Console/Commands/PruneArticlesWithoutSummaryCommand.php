<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;

class PruneArticlesWithoutSummaryCommand extends Command
{
    protected $signature = 'app:prune-articles';

    protected $description = '立即刪除所有沒有摘要、未分析或可信度為0%的文章';

    public function handle()
    {
        $this->info('開始檢查並清理無摘要、未分析、可信度為0%或異常內容的文章...');

        $articlesToDelete = Article::where(function($q) {
            $q->whereNull('summary')
              ->orWhere('summary', '')
              ->orWhere('summary', 'like', '%{emptyPanelMsg}%')
              ->orWhereNull('image_url')
              ->orWhere('image_url', '')
              ->orWhere('image_url', 'like', '%{emptyPanelMsg}%')
              ->orWhereRaw('CHAR_LENGTH(title) < 8')
              ->orWhereRaw('CHAR_LENGTH(content) < 500')
              ->orWhereRaw('CHAR_LENGTH(image_url) < 10')
              ->orWhereNull('title')
              ->orWhere('title', '')
              ->orWhere('title', 'like', '%{emptyPanelMsg}%')
              ->orWhereNull('content')
              ->orWhere('content', '')
              ->orWhere('content', 'like', '%{emptyPanelMsg}%')
              ->orWhereNull('credibility_score')
              ->orWhere('credibility_score', 0);
        })->get();

        if ($articlesToDelete->isEmpty()) {
            $this->info('所有文章都已成功生成摘要、完成分析且內容正常，無需清理。');
            return 0;
        }

        $count = $articlesToDelete->count();
        $this->warn("找到 {$count} 篇無摘要、未分析、可信度為0%或異常內容的文章，準備立即刪除...");

        foreach ($articlesToDelete as $article) {
            $credibilityInfo = $article->credibility_score !== null ? "可信度: {$article->credibility_score}%" : "未分析";
            $this->line("正在刪除文章 ID: {$article->id} - 標題: {$article->title} - {$credibilityInfo}");
            $article->delete();
        }

        $this->info("清理完成！共刪除了 {$count} 篇文章。");
        return 0;
    }
}