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
git commit -m "fix: vite.config.js 的 build 問題"

# 推送到 GitHub
echo "推送到 GitHub..."
git push origin main

echo "完成！變更已成功上傳到 GitHub" 