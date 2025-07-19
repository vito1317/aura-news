<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


$categoriesToFetch = ['科技', '政治', '財經', '娛樂', '運動', '生活'];

foreach ($categoriesToFetch as $category) {
    Schedule::command("app:fetch-news '{$category}' --api=newsapi --language=zh --size=20")
             ->hourly() 
             ->withoutOverlapping()
             ->appendOutputTo(storage_path('logs/newsapi_cron.log'));
}

foreach ($categoriesToFetch as $category) {
    Schedule::command("app:fetch-news '{$category}' --api=newsdata --language=zh")
             ->hourlyAt(30)
             ->withoutOverlapping()
             ->appendOutputTo(storage_path('logs/newsdata_cron.log'));
}

Schedule::command('app:prune-articles')->hourlyAt(15);


