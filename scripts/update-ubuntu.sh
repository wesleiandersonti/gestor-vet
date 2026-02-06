#!/usr/bin/env bash
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/gestor-vet}

cd "$APP_DIR"

git pull

composer install --no-dev --optimize-autoloader
npm install
npm run prod

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload apache2
fi

echo "Atualizacao concluida"
