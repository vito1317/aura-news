<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $article->title }} - Aura News</title>
    <meta property="og:title" content="{{ $article->title }}" />
    <meta property="og:description" content="{{ $article->summary ?? $article->title }}" />
    <meta property="og:type" content="article" />
    <meta property="og:image" content="{{ $article->image_url ?? url('/favicon.ico') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $article->title }}" />
    <meta name="twitter:description" content="{{ $article->summary ?? $article->title }}" />
    <meta name="twitter:image" content="{{ $article->image_url ?? url('/favicon.ico') }}" />
    <script>
      // 若是一般使用者，1 秒後自動跳轉到 SPA
      if (!/bot|crawler|spider|facebookexternalhit|slackbot|twitterbot|bingbot|googlebot|line/i.test(navigator.userAgent)) {
        setTimeout(function() {
          window.location.href = 'https://news.vito1317.com/articles/{{ $article->id }}';
        }, 1000);
      }
    </script>
</head>
<body>
    <h1>{{ $article->title }}</h1>
    <p>{{ $article->summary }}</p>
</body>
</html> 