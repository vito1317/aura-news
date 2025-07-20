# Aura News - AI 驅動的雙 API 新聞平台

**官方網站**：[https://news.vito1317.com](https://news.vito1317.com)  
**聯絡信箱**：service@vito1317.com

Aura News 是一個現代化全端新聞平台，結合 **雙 API 自動新聞抓取**、AI 智能內容生成、RESTful API、前台新聞瀏覽、分類、搜尋、管理後台、SEO 自動化與 CI/CD 部署。
專為現代新聞媒體、內容平台、教育與個人開發者設計，讓 AI 重新定義新聞閱讀體驗。

---

## 目錄
- [產品特色](#產品特色)
- [專案結構](#專案結構)
- [快速安裝](#快速安裝)
- [主要功能](#主要功能)
- [技術棧](#技術棧)
- [app 目錄分層說明](#app-目錄分層說明)
- [新聞 API 與 AI 整合](#新聞-api-與-ai-整合)
- [自動化排程與部署](#自動化排程與部署)
- [Supervisor 任務守護教學](#supervisor-任務守護教學)
- [環境變數設定](#環境變數設定)
- [適用場景](#適用場景)
- [貢獻與授權](#貢獻與授權)

---

## 產品特色

- **雙 API 新聞自動化**：整合 NewsAPI.org 與 NewsData.io，確保新聞來源多樣性與即時性
- **AI 驅動內容生成**：Gemini AI 自動產生新聞內容、摘要、可信度分析
- **智能排程系統**：Laravel Schedule + Crontab，每小時自動抓取、清理、分析新聞
- **RESTful API**：完整的文章、分類、搜尋、用戶、AI 查證 API
- **RWD 響應式設計**：桌機/手機皆美觀，TailwindCSS 打造現代化 UI
- **SEO 自動化**：動態 meta 標籤、sitemap.xml 自動產生
- **自動化部署**：GitHub Actions + SSH/密碼，支援一鍵部署
- **權限控管**：JWT/Token、Sanctum、管理員後台
- **完整日誌與錯誤處理**：API、排程、AI 任務全程追蹤

---

## 專案結構

```
/ (本專案)
├── aura-news-backend/   # Laravel 12.x + PHP 8.x 後端 API 與管理
├── aura-news-frontend/  # Vue 3 + Vite 前端 SPA
```

---

## 快速安裝

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
crontab -e
# 添加
* * * * * cd /path/to/aura-news/aura-news-backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 主要功能

### Backend (aura-news-backend)
- **雙 API 新聞自動抓取**：NewsAPI.org、NewsData.io
- **AI 新聞自動撰寫**：Gemini AI 產生新聞內容、摘要
- **AI 可信度分析**：自動分析新聞可信度分數
- **RESTful API**：文章、分類、搜尋、用戶、AI 查證
- **用戶認證與權限**：JWT/Token、Sanctum、管理員後台
- **自動化排程**：Laravel Schedule + Crontab
- **自動化測試**：PHPUnit
- **日誌與錯誤追蹤**：API、排程、AI 任務全程記錄

### Frontend (aura-news-frontend)
- **SPA 前台新聞網站**：Vue 3 + Vite + TailwindCSS
- **首頁焦點新聞、最新/熱門新聞**
- **分類瀏覽、文章詳情、關鍵字搜尋**
- **AI 查證工具**：即時可信度分析、查證來源顯示
- **RWD 響應式設計**：桌機/手機皆美觀
- **SEO 最佳化**：動態 meta 標籤、sitemap.xml
- **社交分享、閱讀進度、熱門度顯示**

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

## app 目錄分層說明

- `Jobs/`：AI 假新聞查證、新聞資料處理、熱門文章計算等背景任務（Laravel queue 任務）
- `Models/`：資料結構模型（Article、AiScanResult、User、Category）
- `Services/`：API 整合服務（NewsData、BraveSearch 等）
- `Http/Controllers/Api/`：RESTful API 控制器（新聞、查證、用戶、後台管理）
- `Http/Controllers/Api/Admin/`：後台管理控制器（文章、分類、儀表板、圖片）
- `Console/Commands/`：artisan 指令與自動化排程（新聞抓取、熱門計算、清理、測試）
- `Observers/Providers/`：事件監控與服務註冊（可擴充）

---

## 新聞 API 與 AI 整合

- **NewsAPI.org**：每小時自動抓取 20 篇新聞
- **NewsData.io**：每小時自動抓取 10 篇新聞
- **Gemini AI**：自動產生新聞內容、摘要、可信度分數
- **AI 查證工具**：即時分析新聞可信度、來源查證

---

## 自動化排程與部署

- **自動新聞抓取**：每小時自動執行
- **自動清理舊文章**：每小時 15 分執行
- **自動可信度分析**：AI 任務自動排程
- **自動化部署**：GitHub Actions + SSH/密碼，支援一鍵部署

---

## Supervisor 任務守護教學

為確保 Laravel queue 任務（如 AI 查證、新聞處理）穩定執行，建議使用 Supervisor 進行守護：

### 1. 安裝 Supervisor
```bash
sudo apt update
sudo apt install supervisor
```

### 2. 建立 queue worker 設定檔
```ini
# /etc/supervisor/conf.d/aura-news-worker.conf
[program:aura-news-worker]
command=php /path/to/aura-news/aura-news-backend/artisan queue:work --sleep=3 --tries=3
process_name=%(program_name)s_%(process_num)02d
numprocs=1
autostart=true
autorestart=true
user=www-data
directory=/path/to/aura-news/aura-news-backend
stdout_logfile=/var/log/supervisor/aura-news-worker.log
stderr_logfile=/var/log/supervisor/aura-news-worker-error.log
environment=APP_ENV=production,QUEUE_CONNECTION=database
stopwaitsecs=3600
```

### 3. 重新載入 Supervisor 並啟動 worker
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start aura-news-worker:*
```

### 4. 常用指令
```bash
sudo supervisorctl status
sudo supervisorctl restart aura-news-worker:*
sudo supervisorctl stop aura-news-worker:*
```

---

## 環境變數設定

```env
APP_NAME=AuraNews
APP_ENV=production
APP_KEY=base64:xxxxxxx
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aura_news
DB_USERNAME=your_username
DB_PASSWORD=your_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

# CORS 與 Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:3000,news.your-domain.com,admin.your-domain.com
SESSION_DOMAIN=.your-domain.com
CORS_ALLOWED_ORIGINS=https://news.your-domain.com,https://admin.your-domain.com

# AI/新聞/搜尋 API
GEMINI_API_KEY=your_gemini_api_key
NEWS_API_KEY=your_newsapi_key
NEWSDATA_API_KEY=your_newsdata_key
GOOGLE_SEARCH_API_KEY=your_google_search_api_key
GOOGLE_SEARCH_ENGINE_ID=your_google_search_engine_id
BRAVE_SEARCH_API_KEY=your_brave_search_api_key

# S3/CDN（如有圖片上傳）
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=ap-northeast-1
AWS_BUCKET=your_bucket
AWS_URL=https://your-cdn-url.com

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Aura News"

# 其他第三方
# 如 Google/Facebook 登入、Analytics、Line Notify ...

# 管理員帳號（如有自動建立）
ADMIN_EMAIL=admin@your-domain.com
ADMIN_PASSWORD=your_admin_password
```

**說明：**
- `SANCTUM_STATEFUL_DOMAINS`：前端 SPA 網域清單，逗號分隔（本地開發與正式環境都要列出）
- `SESSION_DOMAIN`：建議設為 `.your-domain.com` 以支援多子網域
- `CORS_ALLOWED_ORIGINS`：允許跨域的前端網址，逗號分隔

---

## 適用場景

- **新聞媒體機構**：現代化新聞網站建設
- **企業新聞中心**：企業內部新聞發布平台
- **內容營銷平台**：自動化內容生成和發布
- **教育/研究**：AI 新聞分析、Web 全端教學
- **個人開發者**：學習、創業、開源貢獻

---

## 貢獻與授權

- 請分支開發並發送 Pull Request
- 問題請用 GitHub Issue 回報
- 本專案採用 [MIT License](LICENSE)

---

*Aura News - 讓 AI 重新定義新聞閱讀體驗，雙 API 保障新聞來源多樣性*

**官方網站**：[https://news.vito1317.com](https://news.vito1317.com)  
**聯絡信箱**：service@vito1317.com

如需更詳細的產品說明，請參考 [PRODUCT_DESCRIPTION.md](./PRODUCT_DESCRIPTION.md)。 