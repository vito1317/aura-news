<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {

        $stats = Article::query()
            ->select(
                DB::raw('count(*) as total_articles'),
                DB::raw('count(case when status = 1 then 1 end) as published_articles'),
                DB::raw('count(case when status = 2 then 1 end) as draft_articles'),
                DB::raw('count(case when status = 3 then 1 end) as pending_articles')
            )
            ->first();

        return response()->json($stats);
    }
}