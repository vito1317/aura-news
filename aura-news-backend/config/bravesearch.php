<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Brave Search API Configuration
    |--------------------------------------------------------------------------
    |
    | 這裡設定 Brave Search API 的相關配置
    |
    */

    'api_key' => env('BRAVE_SEARCH_API_KEY', null),
    
    'base_url' => env('BRAVE_SEARCH_BASE_URL', 'https://api.search.brave.com/res/v1'),
    
    'endpoints' => [
        'web_search' => '/web/search',
        'news_search' => '/news/search',
        'image_search' => '/images/search',
    ],
    
    'default_headers' => [
        'Accept' => 'application/json',
        'Accept-Encoding' => 'gzip',
    ],
    
    'timeout' => env('BRAVE_SEARCH_TIMEOUT', 10),
    
    'max_results' => env('BRAVE_SEARCH_MAX_RESULTS', 3),
    
    'language' => env('BRAVE_SEARCH_LANGUAGE', 'zh-TW'),
    
    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | 當 Google Search API 失敗時的備用設定
    |
    */
    
    'enable_fallback' => env('BRAVE_SEARCH_ENABLE_FALLBACK', true),
    
    'fallback_on_errors' => [
        429, // Too Many Requests
        403, // Forbidden
        500, // Internal Server Error
        502, // Bad Gateway
        503, // Service Unavailable
    ],
]; 