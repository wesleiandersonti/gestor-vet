#!/usr/bin/env bash
set -euo pipefail

if ! command -v lsb_release >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y lsb-release
fi

UBU=$(lsb_release -rs)
echo "Ubuntu ${UBU}"

sudo apt-get update
sudo apt-get install -y git unzip curl ca-certificates software-properties-common
sudo apt-get install -y php php-cli php-mbstring php-xml php-curl php-zip php-mysql php-bcmath php-intl
sudo apt-get install -y mysql-server

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl enable --now mysql
else
  sudo service mysql start
fi

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
  sudo apt-get install -y nodejs
fi

DB_NAME=${DB_NAME:-gestorvet}
DB_USER=${DB_USER:-gestorvet}
DB_PASS=${DB_PASS:-gestorvet}

sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
  else
    touch .env
  fi
fi

set_env() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    echo "${key}=${value}" >> .env
  fi
}

set_env "DB_CONNECTION" "mysql"
set_env "DB_HOST" "127.0.0.1"
set_env "DB_PORT" "3306"
set_env "DB_DATABASE" "${DB_NAME}"
set_env "DB_USERNAME" "${DB_USER}"
set_env "DB_PASSWORD" "${DB_PASS}"

if [ -f composer.json ]; then
  composer install
fi

if [ -f package.json ]; then
  npm install
fi

if [ -f artisan ]; then
  php artisan key:generate
fi

if [ -f package.json ]; then
  npm run dev
fi

if [ -f artisan ]; then
  php artisan migrate
fi

echo "Instalacao concluida. Ajuste o .env se necessario."
