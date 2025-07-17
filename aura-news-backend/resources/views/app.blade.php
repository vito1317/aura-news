<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura News</title>

    {{-- 使用 Vite 的輔助函數來載入 CSS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    <div id="app"></div>
  </body>
</html>