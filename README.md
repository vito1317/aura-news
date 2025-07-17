# Aura News

Aura News 是一個全端新聞平台專案，包含後端（Laravel, PHP）與前端（Vue 3, Vite, TailwindCSS），支援 AI 自動抓取與撰寫新聞、RESTful API、現代化前台介面。

## 專案結構

```
/ (本專案)
├── aura-news-backend/   # Laravel/PHP 後端專案
├── aura-news-frontend/  # Vue3/Vite 前端專案
```

- **aura-news-backend**：新聞 API、AI 抓取與撰寫、用戶/分類/文章管理等
- **aura-news-frontend**：新聞前台、分類、登入、管理介面等

## 技術棧
- PHP 8.x, Laravel 12.x
- MySQL / MariaDB
- Node.js 20+
- Vue 3, Vite, TailwindCSS
- Composer, npm
- 整合 Gemini AI API

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
# 設定 .env 內資料庫與 AI 服務金鑰
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

## 主要功能
- AI 自動抓取與撰寫新聞（後端）
- RESTful API
- 新聞/分類/用戶管理
- 前台新聞瀏覽、分類、搜尋
- 響應式設計(RWD)
- SEO sitemap.xml 自動化產生，支援 slug 路徑
- GitHub Actions 自動化部署，支援 SSH/密碼、排除 .env、dist、storage 等敏感目錄

## 協作與貢獻
- 請分支開發並發送 Pull Request
- 問題請用 GitHub Issue 回報

## 授權條款
本專案採用 [MIT License](License)。 