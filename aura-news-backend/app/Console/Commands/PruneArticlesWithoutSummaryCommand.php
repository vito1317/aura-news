<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;

class PruneArticlesWithoutSummaryCommand extends Command
{
    protected $signature = 'app:prune-articles';

    protected $description = '立即刪除所有沒有摘要的文章';

    public function handle()
    {
        $this->info('開始檢查並清理無摘要或異常內容的文章...');

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
              ->orWhere('content', 'like', '%{emptyPanelMsg}%');
        })->get();

        if ($articlesToDelete->isEmpty()) {
            $this->info('所有文章都已成功生成摘要且內容正常，無需清理。');
            return 0;
        }

        $count = $articlesToDelete->count();
        $this->warn("找到 {$count} 篇無摘要或異常內容的文章，準備立即刪除...");

        foreach ($articlesToDelete as $article) {
            $this->line("正在刪除文章 ID: {$article->id} - 標題: {$article->title}");
            $article->delete();
        }

        $this->info("清理完成！共刪除了 {$count} 篇文章。");
        return 0;
    }
}