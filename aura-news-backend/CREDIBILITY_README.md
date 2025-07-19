# 文章可信度分析功能

這個功能為新聞文章提供自動化的 AI 可信度分析，幫助用戶判斷新聞內容的可信度。

## 功能特點

1. **自動分析**: 當文章生成時自動進行可信度掃描
2. **手動觸發**: 可以手動觸發特定文章的可信度分析
3. **實時進度**: 提供分析進度的實時查詢
4. **多源驗證**: 結合站內新聞、NewsAPI 和 Google 搜尋進行綜合查證

## 資料庫變更

新增了以下欄位到 `articles` 表：

- `credibility_analysis` (longText): AI 分析的詳細結果
- `credibility_score` (integer): 可信度分數 (0-100)
- `credibility_checked_at` (timestamp): 分析時間

## API 端點

### 1. 獲取文章可信度分析結果

```
GET /api/articles/{articleId}/credibility
```

**回應範例:**
```json
{
    "has_analysis": true,
    "credibility_score": 85,
    "credibility_analysis": "詳細的分析內容...",
    "credibility_checked_at": "2025-01-19T10:30:00.000000Z",
    "article_title": "文章標題",
    "article_source": "https://example.com/article"
}
```

### 2. 觸發文章可信度分析

```
POST /api/articles/{articleId}/credibility/analyze
```

**回應範例:**
```json
{
    "message": "可信度分析已開始",
    "taskId": "uuid-string",
    "article_id": 123
}
```

### 3. 查詢分析進度

```
GET /api/articles/credibility/progress/{taskId}
```

**回應範例:**
```json
{
    "progress": "AI 綜合查證中",
    "result": null
}
```

## 使用方式

### 前端整合

1. **檢查文章是否已有可信度分析:**
```javascript
const response = await fetch(`/api/articles/${articleId}/credibility`);
const data = await response.json();

if (data.has_analysis) {
    // 顯示可信度分數和分析結果
    displayCredibilityScore(data.credibility_score);
    displayCredibilityAnalysis(data.credibility_analysis);
} else {
    // 顯示觸發分析的按鈕
    showAnalyzeButton(articleId);
}
```

2. **觸發可信度分析:**
```javascript
const response = await fetch(`/api/articles/${articleId}/credibility/analyze`, {
    method: 'POST'
});
const data = await response.json();

if (data.taskId) {
    // 開始輪詢進度
    pollProgress(data.taskId);
}
```

3. **輪詢分析進度:**
```javascript
async function pollProgress(taskId) {
    const response = await fetch(`/api/articles/credibility/progress/${taskId}`);
    const data = await response.json();
    
    if (data.progress === '完成') {
        // 分析完成，重新獲取結果
        getCredibility(articleId);
    } else {
        // 繼續輪詢
        setTimeout(() => pollProgress(taskId), 5000);
    }
}
```

### 可信度分數說明

- **90-100%**: 極高可信度
- **70-89%**: 高可信度
- **50-69%**: 中等可信度
- **30-49%**: 低可信度
- **0-29%**: 極低可信度

## 自動化流程

當使用 `FetchNewsCommand` 抓取新聞時，系統會：

1. 抓取新聞內容
2. 生成 AI 摘要
3. **自動進行可信度分析** (新增)
4. 儲存所有結果到資料庫

## 測試

可以使用提供的示例頁面進行測試：

```
http://your-domain.com/article-credibility-example.html
```

## 注意事項

1. 可信度分析需要較長時間，建議使用非同步處理
2. 24 小時內重複分析同一文章會被拒絕
3. 需要確保 NewsAPI 和 Google Search API 的配置正確
4. 分析結果會自動儲存到資料庫，無需額外處理

## 錯誤處理

常見錯誤及解決方案：

- **"此文章在 24 小時內已進行過可信度分析"**: 等待 24 小時後再試
- **"主文擷取失敗"**: 檢查文章內容是否完整
- **"請求過於頻繁"**: 降低請求頻率，每小時最多 10 次
- **"NewsAPI 查詢失敗"**: 檢查 NEWS_API_KEY 配置
- **"Google Search API 查詢失敗"**: 檢查 Google Search API 配置 