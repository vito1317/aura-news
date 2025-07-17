# Aura News Frontend

Aura News Frontend 是一個以 Vite + Vue 3 + TailwindCSS 為基礎的現代化新聞前台專案，提供快速、響應式的使用者介面，並與後端 API 整合。

## 技術棧
- [Vue 3](https://vuejs.org/)
- [Vite](https://vitejs.dev/)
- [TailwindCSS](https://tailwindcss.com/)
- [ESLint](https://eslint.org/) & [Prettier](https://prettier.io/)
- [Node.js](https://nodejs.org/) 16+

## 目錄結構
- `src/`：主要前端程式碼
  - `assets/`：靜態資源（圖片、CSS、SVG 等）
  - `components/`：可重用元件（如 TheHeader, ArticleCard, SidebarWidget 等）
  - `views/`：頁面元件（如 HomeView, ArticleDetailView, LoginView, CategoryView, SearchView）
    - `admin/`：後台相關頁面
  - `layouts/`：版型元件（如 AdminLayout）
  - `stores/`：Pinia 狀態管理（如 auth.js, counter.js）
  - `router/`：前端路由設定（index.js）
  - `App.vue`：主應用元件
  - `main.js`：應用進入點
- `public/`：靜態資源與入口 HTML（index.html, favicon.ico, .htaccess）
- `dist/`：建置後產物
- `node_modules/`：npm 套件
- 設定檔：
  - `package.json`、`package-lock.json`：依賴管理
  - `vite.config.js`：Vite 設定
  - `tailwind.config.js`、`postcss.config.js`：CSS 工具設定
  - `eslint.config.js`、`.prettierrc.json`：程式碼風格
  - `jsconfig.json`：VSCode 路徑提示
  - `.editorconfig`、`.gitignore`、`.gitattributes`：專案協作設定

## 安裝與啟動
1. 複製專案
   ```bash
   git clone https://github.com/vito1317/aura-news-frontend.git
   cd aura-news-frontend
   ```
2. 安裝依賴
   ```bash
   npm install
   ```
3. 啟動開發伺服器
   ```bash
   npm run dev
   ```

## 建置與部署
- 建置生產環境：
  ```bash
  npm run build
  ```
- 部署：將 `dist/` 目錄內容上傳至靜態主機即可

## 程式碼檢查與格式化
- Lint：
  ```bash
  npm run lint
  ```
- 格式化：
  ```bash
  npm run format
  ```

## 授權條款
本專案採用 [MIT License](https://opensource.org/licenses/MIT)。

## 聯絡/貢獻
如需協助、回報問題或貢獻程式碼，請於 GitHub Issue 或 Pull Request 提出。
