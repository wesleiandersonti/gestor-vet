#!/usr/bin/env bash
set -euo pipefail

if ! command -v lsb_release >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y lsb-release
fi

UBU=$(lsb_release -rs)
echo "Ubuntu ${UBU}"

if [ "${UBU}" != "22.04" ]; then
  echo "Aviso: este instalador foi preparado para Ubuntu 22.04."
fi

sudo apt-get update
sudo apt-get install -y git unzip curl ca-certificates software-properties-common build-essential
sudo apt-get install -y php php-cli php-fpm php-mbstring php-xml php-curl php-zip php-mysql php-bcmath php-intl php-gd php-soap php-readline php-redis
sudo apt-get install -y mysql-server nginx

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl enable --now mysql nginx
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

PUBLIC_IP=$(curl -s https://api.ipify.org || true)
if [ -z "$PUBLIC_IP" ]; then
  PUBLIC_IP=$(curl -s https://ifconfig.me || true)
fi
if [ -z "$PUBLIC_IP" ]; then
  echo "Nao foi possivel detectar o IP publico automaticamente."
  exit 1
fi

echo "Informe o dominio (opcional)."
echo "Se deixar em branco, o acesso sera pelo IP: ${PUBLIC_IP}"
read -r DOMAIN

SERVER_NAME="$PUBLIC_IP"
APP_URL="http://${PUBLIC_IP}"

if [ -n "$DOMAIN" ]; then
  SERVER_NAME="$DOMAIN"
  APP_URL="http://${DOMAIN}"
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
set_env "APP_URL" "${APP_URL}"

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

sudo tee /etc/nginx/sites-available/gestor-vet >/dev/null <<EOF
server {
  listen 80;
  server_name ${SERVER_NAME};
  root ${PWD}/public;

  index index.php index.html;
  client_max_body_size 64M;

  location / {
    try_files \$uri \$uri/ /index.php?\$query_string;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
  }

  location ~* \.(jpg|jpeg|gif|png|css|js|ico|svg|woff|woff2|ttf|eot)$ {
    expires max;
    log_not_found off;
  }
}
EOF

sudo ln -sf /etc/nginx/sites-available/gestor-vet /etc/nginx/sites-enabled/gestor-vet
sudo nginx -t
sudo systemctl reload nginx

if [ -n "$DOMAIN" ]; then
  echo "Deseja instalar SSL (LetsEncrypt) agora? (s/N)"
  read -r INSTALL_SSL
  if [ "$INSTALL_SSL" = "s" ] || [ "$INSTALL_SSL" = "S" ]; then
    sudo apt-get install -y certbot python3-certbot-nginx
    echo "Informe o email para o certificado (opcional)."
    read -r CERT_EMAIL
    if [ -n "$CERT_EMAIL" ]; then
      sudo certbot --nginx -d "$DOMAIN" --agree-tos -m "$CERT_EMAIL" --redirect
    else
      sudo certbot --nginx -d "$DOMAIN" --agree-tos --register-unsafely-without-email --redirect
    fi
  fi
else
  echo "SSL nao pode ser emitido para IP. Para SSL, use um dominio."
fi

echo "Instalacao concluida: ${APP_URL}"
