# Aura News

Aura News 是一個現代化全端新聞平台，結合 **雙 API 自動新聞抓取**、AI 自動新聞撰寫、RESTful API、前台新聞瀏覽、分類、搜尋、管理後台、SEO 自動化與 CI/CD 部署。

---

## 專案結構

```
/ (本專案)
├── aura-news-backend/   # Laravel 12.x + PHP 8.x 後端 API 與管理
├── aura-news-frontend/  # Vue 3 + Vite 前端 SPA
```

---

## 主要功能

### Backend (aura-news-backend)
- **雙 API 新聞自動抓取**：整合 NewsAPI.org 與 NewsData.io，自動抓取最新新聞
- **AI 新聞自動撰寫**：整合 Gemini AI，自動產生新聞內容與摘要
- **自動化排程系統**：Laravel 排程 + Crontab，每小時自動抓取新聞
- **RESTful API**：提供文章、分類、搜尋、用戶等 API
- **用戶認證**：支援 JWT/Token 登入、權限控管（含管理員）
- **後台管理**：文章、分類、用戶、AI 產生新聞、圖片上傳、儀表板
- **資料庫遷移與種子**：一鍵建表與假資料
- **自動化測試**：整合 PHPUnit
- **CORS 與 API 安全性**：支援跨域、API 金鑰、Sanctum
- **自動可信度掃描**：AI 自動分析新聞可信度

### Frontend (aura-news-frontend)
- **SPA 前台新聞網站**：Vue 3 + Vite + TailwindCSS
- **首頁焦點新聞 Banner、最新/熱門新聞區塊**
- **分類瀏覽、文章詳情、關鍵字搜尋**
- **RWD 響應式設計**：桌機/手機皆美觀
- **登入/登出/權限導向**
- **SEO 最佳化**：動態 meta 標籤、sitemap.xml 自動產生（API 取 slug）
- **自動化部署支援**：GitHub Actions + SSH/密碼

---

## 技術棧
- **後端**：PHP 8.x, Laravel 12.x, MySQL/MariaDB, Composer, PHPUnit, Sanctum
- **前端**：Vue 3, Vite, TailwindCSS, Pinia, Axios, @vueuse/head
- **AI 整合**：Gemini AI API
- **新聞 API**：NewsAPI.org, NewsData.io
- **CI/CD**：GitHub Actions, sshpass, rsync
- **排程系統**：Laravel Schedule, Crontab
- **其他**：Node.js 20+, npm

---

## 安裝與啟動

### 1. 下載專案
```bash
git clone https://github.com/vito1317/aura-news.git
cd aura-news
```

### 2. 安裝後端
```bash
cd aura-news-backend
composer install
npm install
cp .env.example .env
php artisan key:generate

# 設定 .env 內資料庫、AI 金鑰、新聞 API 金鑰
# NEWS_API_KEY=your_newsapi_key
# NEWSDATA_API_KEY=your_newsdata_key
# GEMINI_API_KEY=your_gemini_key

php artisan migrate --seed
npm run build
php artisan serve
```

### 3. 安裝前端
```bash
cd ../aura-news-frontend
npm install
npm run dev
```

### 4. 設定自動化排程
```bash
# 編輯 crontab
crontab -e

# 添加以下內容
* * * * * cd /path/to/aura-news/aura-news-backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 新聞 API 整合

### NewsAPI.org
- **功能**：抓取全球新聞，支援多語言
- **設定**：在 `.env` 中設定 `NEWS_API_KEY`
- **排程**：每小時整點執行，抓取 20 篇文章

### NewsData.io
- **功能**：抓取全球新聞，支援多語言
- **設定**：在 `.env` 中設定 `NEWSDATA_API_KEY`
- **排程**：每小時 30 分執行，抓取 10 篇文章（API 預設值）

### 支援分類
- 科技、政治、財經、娛樂、運動、生活

---

## 自動化排程系統

### 排程時間表
- **00:00, 01:00, 02:00...** - NewsAPI 抓取所有分類
- **00:30, 01:30, 02:30...** - NewsData API 抓取所有分類
- **00:15, 01:15, 02:15...** - 清理舊文章
- **每分鐘** - Laravel 排程檢查

### 監控命令
```bash
# 查看所有排程任務
php artisan schedule:list

# 手動執行排程
php artisan schedule:run

# 查看 NewsAPI 日誌
tail -f storage/logs/newsapi_cron.log

# 查看 NewsData API 日誌
tail -f storage/logs/newsdata_cron.log

# 查看主日誌
tail -f storage/logs/laravel.log
```

### 手動執行命令
```bash
# 手動抓取 NewsAPI
php artisan app:fetch-news 科技 --api=newsapi --language=zh --size=20

# 手動抓取 NewsData API
php artisan app:fetch-news 科技 --api=newsdata --language=zh

# 清理舊文章
php artisan app:prune-articles
```

---

## 特色與最佳實踐
- **雙 API 新聞自動化**：NewsAPI.org + NewsData.io，確保新聞來源多樣性
- **AI 新聞自動化**：Gemini AI 產生新聞摘要與內容
- **自動可信度掃描**：AI 自動分析新聞可信度分數
- **智能排程系統**：避免 API 衝突，優化執行時間
- **SEO 自動化**：build 時自動產生 sitemap.xml，支援 slug 路徑
- **RWD 響應式設計**：首頁卡片/列表/Banner 圖片高度統一
- **自動化部署**：GitHub Actions + SSH/密碼，排除 .env、dist、storage 等敏感目錄
- **API 安全性**：Sanctum、CORS、權限控管
- **現代化前後端分離架構**
- **完整的錯誤處理與日誌記錄**

---

## 環境變數設定

### 必要設定
```env
# 資料庫
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aura_news
DB_USERNAME=your_username
DB_PASSWORD=your_password

# AI API
GEMINI_API_KEY=your_gemini_api_key

# 新聞 API
NEWS_API_KEY=your_newsapi_key
NEWSDATA_API_KEY=your_newsdata_key
```

---

## 協作與貢獻
- 請分支開發並發送 Pull Request
- 問題請用 GitHub Issue 回報

## 授權條款
本專案採用 [MIT License](LICENSE)。 