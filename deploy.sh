#!/bin/bash
cd /var/www/aura-news/aura-news-backend
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan storage:link
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd /var/www/aura-news/aura-news-frontend
npm install --legacy-peer-deps
#npm run build
