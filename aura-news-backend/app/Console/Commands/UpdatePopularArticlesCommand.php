<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UpdatePopularArticles;

class UpdatePopularArticlesCommand extends Command
{
    protected $signature = 'app:update-popular-articles {--article-id=}';

    protected $description = '更新所有文章的熱門度分數';

    public function handle()
    {
        $articleId = $this->option('article-id');
        
        if ($articleId) {
            $this->info("開始更新文章 ID {$articleId} 的熱門度分數...");
            UpdatePopularArticles::dispatch($articleId);
            $this->info("文章 ID {$articleId} 的熱門度分數更新工作已加入隊列");
        } else {
            $this->info('開始更新所有文章的熱門度分數...');
            UpdatePopularArticles::dispatch();
            $this->info('所有文章的熱門度分數更新工作已加入隊列');
        }
        
        $this->info('請運行 php artisan queue:work 來處理隊列工作');
        
        return 0;
    }
}
