#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_DIR=${APP_DIR:-$DEFAULT_APP_DIR}

if [ ! -d "$APP_DIR" ] || [ ! -f "$APP_DIR/artisan" ]; then
  echo "Diretorio do projeto invalido: $APP_DIR"
  echo "Defina APP_DIR com o caminho correto."
  exit 1
fi

cd "$APP_DIR"

git pull --ff-only

PHP_MAJOR_MINOR=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
if [ "$PHP_MAJOR_MINOR" != "8.2" ]; then
  echo "PHP atual: $PHP_MAJOR_MINOR"
  echo "Este projeto requer PHP 8.2. Atualize o PHP da VM e rode novamente."
  exit 1
fi

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
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
