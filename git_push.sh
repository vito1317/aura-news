#!/bin/bash

# 切換到專案目錄
cd /var/www/aura-news

# 檢查 Git 狀態
echo "檢查 Git 狀態..."
git status

# 添加所有變更
echo "添加所有變更..."
git add .

# 提交變更
echo "提交變更..."
git commit -m "feat: 更新 README.md 和 PRODUCT_DESCRIPTION.md

- 新增雙 API 新聞抓取功能說明 (NewsAPI.org + NewsData.io)
- 新增自動化排程系統說明 (Laravel Schedule + Crontab)
- 新增自動可信度掃描功能說明
- 更新技術棧和環境變數設定
- 新增監控命令和手動執行命令
- 完善安裝指南和特色功能說明"

# 推送到 GitHub
echo "推送到 GitHub..."
git push origin main

echo "完成！變更已成功上傳到 GitHub" 