<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


$categoriesToFetch = ['科技', '政治', '財經', '娛樂', '運動', '生活'];

foreach ($categoriesToFetch as $category) {
    Schedule::command("app:fetch-news '{$category}'")
             ->hourly() 
             ->withoutOverlapping(); 
}

Schedule::command('app:prune-articles')->hourlyAt(15);


