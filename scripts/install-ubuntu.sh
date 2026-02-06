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
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get install -y php8.2 php8.2-cli libapache2-mod-php8.2 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-mysql php8.2-bcmath php8.2-intl php8.2-gd php8.2-soap php8.2-readline php8.2-redis
sudo apt-get install -y mysql-server apache2

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl enable --now mysql apache2
else
  sudo service mysql start
fi

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

sudo a2dismod php8.1 >/dev/null 2>&1 || true
sudo a2enmod php8.2
sudo systemctl restart apache2

if ! command -v node >/dev/null 2>&1; then
  curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
  sudo apt-get install -y nodejs
fi

LOCAL_IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i=1; i<=NF; i++) if ($i == "src") { print $(i+1); exit }}' || true)
if [ -z "$LOCAL_IP" ]; then
  LOCAL_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || true)
fi
if [ -z "$LOCAL_IP" ]; then
  LOCAL_IP="127.0.0.1"
fi

PUBLIC_IP=$(curl -fsS https://api.ipify.org 2>/dev/null || true)
if [ -z "$PUBLIC_IP" ]; then
  PUBLIC_IP=$(curl -fsS https://ifconfig.me 2>/dev/null || true)
fi

ACCESS_MODE=${ACCESS_MODE:-}
DOMAIN=${DOMAIN:-}

if [ -z "$ACCESS_MODE" ]; then
  echo "Escolha o tipo de acesso inicial:"
  echo "1) Dominio"
  echo "2) IP local da VM (padrao)"
  echo "3) IP publico da VM"
  read -r ACCESS_MODE
fi

SERVER_NAME="$LOCAL_IP"
APP_URL="http://${LOCAL_IP}"
HOST_MODE_RESOLVED="local"

case "$ACCESS_MODE" in
  "1")
    if [ -z "$DOMAIN" ]; then
      echo "Informe o dominio (exemplo: app.seudominio.com):"
      read -r DOMAIN
    fi
    if [ -z "$DOMAIN" ]; then
      echo "Dominio nao informado. Usando IP local: ${LOCAL_IP}"
    else
      SERVER_NAME="$DOMAIN"
      APP_URL="http://${DOMAIN}"
      HOST_MODE_RESOLVED="domain"
    fi
    ;;
  "3")
    if [ -n "$PUBLIC_IP" ]; then
      SERVER_NAME="$PUBLIC_IP"
      APP_URL="http://${PUBLIC_IP}"
      HOST_MODE_RESOLVED="public"
    else
      echo "Nao foi possivel detectar IP publico. Usando IP local: ${LOCAL_IP}"
    fi
    ;;
  *)
    echo "Usando IP local: ${LOCAL_IP}"
    ;;
esac

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
  if [ -f composer.lock ]; then
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction
  else
    COMPOSER_ALLOW_SUPERUSER=1 composer update --no-interaction
  fi
fi

if [ -f package.json ]; then
  if [ -f package-lock.json ]; then
    npm ci
  else
    npm install
  fi
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

sudo a2enmod rewrite
sudo tee /etc/apache2/sites-available/gestor-vet.conf >/dev/null <<EOF
<VirtualHost *:80>
  ServerName ${SERVER_NAME}
  DocumentRoot ${PWD}/public

  <Directory ${PWD}/public>
    AllowOverride All
    Require all granted
  </Directory>

  ErrorLog \${APACHE_LOG_DIR}/gestor-vet-error.log
  CustomLog \${APACHE_LOG_DIR}/gestor-vet-access.log combined
</VirtualHost>
EOF

sudo a2ensite gestor-vet.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2

if [ "$HOST_MODE_RESOLVED" = "domain" ]; then
  echo "Deseja instalar SSL (LetsEncrypt) agora? (s/N)"
  read -r INSTALL_SSL
  if [ "$INSTALL_SSL" = "s" ] || [ "$INSTALL_SSL" = "S" ]; then
    sudo apt-get install -y certbot python3-certbot-apache
    echo "Informe o email para o certificado (opcional)."
    read -r CERT_EMAIL
    if [ -n "$CERT_EMAIL" ]; then
      sudo certbot --apache -d "$DOMAIN" --agree-tos -m "$CERT_EMAIL" --redirect
    else
      sudo certbot --apache -d "$DOMAIN" --agree-tos --register-unsafely-without-email --redirect
    fi
  fi
else
  echo "SSL nao pode ser emitido para IP. Para SSL, use a opcao de dominio."
fi

echo "Instalacao concluida: ${APP_URL}"
