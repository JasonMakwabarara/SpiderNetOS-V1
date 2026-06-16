#!/bin/bash
# SpiderNetOS Production Deployment Script
# Server: 5.223.68.233

set -e

echo "?? SpiderNetOS Production Deployment"
echo "===================================="

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

# Step 1: Update code
echo -e "\n?? Step 1: Updating code..."
cd /var/www/spidernet-final
git pull

# Step 2: Install PHP dependencies
echo -e "\n?? Step 2: Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Step 3: Install NPM dependencies and build frontend
echo -e "\n?? Step 3: Building frontend..."
cd cockpit
npm install
npm run build
cd ..

# Step 4: Update environment
echo -e "\n?? Step 4: Updating environment..."
cp .env.production .env
php artisan key:generate

# Step 5: Run migrations
echo -e "\n?? Step 5: Running migrations..."
php artisan migrate --force

# Step 6: Clear caches
echo -e "\n?? Step 6: Clearing caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 7: Set permissions
echo -e "\n?? Step 7: Setting permissions..."
chmod -R 755 storage bootstrap/cache data
chmod 777 data

# Step 8: Restart PHP-FPM
echo -e "\n?? Step 8: Restarting PHP-FPM..."
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# Step 9: Run smoke tests
echo -e "\n?? Step 9: Running smoke tests..."
curl -f http://localhost:8000/api/health || exit 1

echo -e "\n${GREEN}? Deployment completed successfully!${NC}"
echo -e "\n?? Access the site at: https://spidernetos.com"