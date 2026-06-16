# SpiderNetOS Production Runbook

## Deployment
```bash
git pull
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache