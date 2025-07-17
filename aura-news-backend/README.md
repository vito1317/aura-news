# Aura News Backend

Aura News Backend 是一個以 Laravel 為核心的新聞後端服務，具備 AI 自動抓取新聞文章與 AI 撰寫新聞的先進功能，並提供 RESTful API，支援新聞管理、分類、用戶認證等，能與前端專案無縫整合。

## 主要功能
- **AI 自動抓取新聞**：定時自動從外部來源抓取新聞，並進行資料清洗與分類。
- **AI 撰寫新聞**：結合 AI 模型自動生成新聞摘要或全新新聞內容。
- 新聞文章 CRUD
- 分類管理
- 用戶註冊、登入、權限控管
- API Token 驗證
- 後台管理介面（可擴充）

## 技術棧
- PHP 8.x
- Laravel 10.x
- MySQL / MariaDB
- Composer
- Node.js & npm（前端資源編譯）
- 整合 AI 服務（Gemini API，可於 `config/gemini.php` 設定）

## 目錄結構
- `app/`：
  - `Console/`：自訂 Artisan 指令（如 AI 抓取、AI 產生新聞）
  - `Http/`：
    - `Controllers/`：API 與後台控制器
    - `Middleware/`：HTTP 請求中介層
  - `Jobs/`：背景任務（如 ProcessArticleData 處理 AI 抓取/生成的新聞）
  - `Models/`：Eloquent 資料模型（Article, Category, User）
  - `Observers/`：模型事件觀察者
  - `Providers/`：服務提供者（如路由、應用註冊）
- `bootstrap/`：框架啟動設定
- `config/`：各類設定檔（資料庫、快取、郵件、認證、AI 服務等）
- `database/`：
  - `migrations/`：資料表結構遷移
  - `seeders/`：預設資料填充
  - `factories/`：測試資料工廠
- `public/`：入口檔案（index.php）、靜態資源、.htaccess
- `resources/`：
  - `views/`：Blade 模板（app.blade.php, welcome.blade.php）
  - `css/`、`js/`：前端資源
- `routes/`：
  - `api.php`：API 路由
  - `web.php`：Web 路由
  - `console.php`：Artisan 指令路由
- `storage/`：快取、日誌、上傳檔案等
- `tests/`：
  - `Feature/`、`Unit/`、`Browser/`：自動化測試
- 其他：
  - `.env.example`：環境變數範本
  - `composer.json`、`package.json`：依賴管理
  - `vite.config.js`：前端資源建構設定

## 安裝與啟動
1. 複製專案
   ```bash
   git clone https://github.com/vito1317/aura-news-backend.git
   cd aura-news-backend
   ```
2. 安裝 PHP 套件
   ```bash
   composer install
   ```
3. 安裝 Node.js 套件（如需前端資源）
   ```bash
   npm install
   ```
4. 複製環境設定檔
   ```bash
   cp .env.example .env
   ```
5. 設定 `.env` 內的資料庫、AI 服務金鑰等資訊
6. 產生應用金鑰
   ```bash
   php artisan key:generate
   ```
7. 執行資料庫遷移與預設資料
   ```bash
   php artisan migrate --seed
   ```
8. 編譯前端資源（如有）
   ```bash
   npm run build
   ```
9. 啟動本地伺服器
   ```bash
   php artisan serve
   ```

## AI 相關操作
- 執行 AI 自動抓取新聞：
  ```bash
  php artisan fetch:news
  ```
- 執行 AI 自動撰寫新聞（如有相關指令）：
  ```bash
  php artisan ai:write-news
  ```
- 可設定排程（Scheduler）自動執行上述任務。

## 測試
- 執行單元與功能測試：
  ```bash
  php artisan test
  ```

## 授權條款
本專案採用 [MIT License](https://opensource.org/licenses/MIT)。

## 聯絡/貢獻
如需協助、回報問題或貢獻程式碼，請於 GitHub Issue 或 Pull Request 提出。
