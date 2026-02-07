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

if ! php -m | grep -qi ioncube; then
  echo "ionCube Loader nao carregado no PHP CLI."
  echo "Instale php8.2-ioncube-loader e rode novamente."
  exit 1
fi

if ! command -v node >/dev/null 2>&1; then
  echo "Node.js nao encontrado. Execute o scripts/install-ubuntu.sh para corrigir."
  exit 1
fi

NODE_MAJOR=$(node -v | sed -E 's/^v([0-9]+).*/\1/')
if [ "$NODE_MAJOR" -lt 20 ]; then
  echo "Node atual: v$NODE_MAJOR"
  echo "Este projeto requer Node.js 20 ou superior. Atualize o Node e rode novamente."
  exit 1
fi

if [ -f composer.lock ]; then
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
else
  echo "Aviso: composer.lock nao encontrado. Executando composer update --no-dev."
  COMPOSER_ALLOW_SUPERUSER=1 composer update --no-dev --optimize-autoloader --no-interaction
fi

if [ -f package-lock.json ]; then
  npm ci --legacy-peer-deps
else
  npm install --legacy-peer-deps
fi
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
