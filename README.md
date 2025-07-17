# Aura News

Aura News 是一個現代化全端新聞平台，結合 AI 自動新聞抓取與撰寫、RESTful API、前台新聞瀏覽、分類、搜尋、管理後台、SEO 自動化與 CI/CD 部署。

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
- **AI 新聞自動抓取與撰寫**：整合 Gemini AI，自動產生新聞內容
- **RESTful API**：提供文章、分類、搜尋、用戶等 API
- **用戶認證**：支援 JWT/Token 登入、權限控管（含管理員）
- **後台管理**：文章、分類、用戶、AI 產生新聞、圖片上傳、儀表板
- **資料庫遷移與種子**：一鍵建表與假資料
- **自動化測試**：整合 PHPUnit
- **CORS 與 API 安全性**：支援跨域、API 金鑰、Sanctum

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
- **CI/CD**：GitHub Actions, sshpass, rsync
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
# 設定 .env 內資料庫、AI 金鑰
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

---

## 特色與最佳實踐
- **AI 新聞自動化**：Gemini AI 產生新聞摘要與內容
- **SEO 自動化**：build 時自動產生 sitemap.xml，支援 slug 路徑
- **RWD 響應式設計**：首頁卡片/列表/Banner 圖片高度統一
- **自動化部署**：GitHub Actions + SSH/密碼，排除 .env、dist、storage 等敏感目錄
- **API 安全性**：Sanctum、CORS、權限控管
- **現代化前後端分離架構**

---

## 協作與貢獻
- 請分支開發並發送 Pull Request
- 問題請用 GitHub Issue 回報

## 授權條款
本專案採用 [MIT License](LICENSE)。 