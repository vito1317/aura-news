<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            '科技' => 'tech', // 手動指定我們想要的 slug
            '政治' => 'politics',
            '財經' => 'finance',
            '娛樂' => 'entertainment',
            '運動' => 'sports',
            '生活' => 'lifestyle'
        ];

        foreach ($categories as $name => $slug) {
            Category::create([
                'name' => $name,
                'slug' => $slug,
            ]);
        }
    }
}