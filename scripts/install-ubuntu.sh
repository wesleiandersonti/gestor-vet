#!/usr/bin/env bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

if [ -t 1 ]; then
  C_RESET="\033[0m"
  C_BLUE="\033[1;34m"
  C_GREEN="\033[1;32m"
  C_YELLOW="\033[1;33m"
  C_RED="\033[1;31m"
else
  C_RESET=""
  C_BLUE=""
  C_GREEN=""
  C_YELLOW=""
  C_RED=""
fi

line() {
  printf '%s\n' "------------------------------------------------------------"
}

apt_update() {
  sudo apt-get -o Acquire::ForceIPv4=true update
}

apt_install() {
  sudo apt-get -o Acquire::ForceIPv4=true install -y "$@"
}

install_ioncube_manual() {
  local php_ext_dir
  local loader_file="ioncube_loader_lin_8.2.so"
  local work_dir="/tmp/ioncube-install"

  php_ext_dir=$(php-config --extension-dir 2>/dev/null || php -i | awk -F'=> ' '/^extension_dir =>/ {print $2; exit}')
  if [ -z "$php_ext_dir" ]; then
    return 1
  fi

  rm -rf "$work_dir"
  mkdir -p "$work_dir"
  curl -fsSL "https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz" -o "$work_dir/ioncube.tar.gz"
  tar -xzf "$work_dir/ioncube.tar.gz" -C "$work_dir"

  if [ ! -f "$work_dir/ioncube/$loader_file" ]; then
    return 1
  fi

  sudo cp "$work_dir/ioncube/$loader_file" "$php_ext_dir/$loader_file"
  echo "zend_extension=$php_ext_dir/$loader_file" | sudo tee /etc/php/8.2/cli/conf.d/00-ioncube.ini >/dev/null

  if [ -d /etc/php/8.2/apache2/conf.d ]; then
    echo "zend_extension=$php_ext_dir/$loader_file" | sudo tee /etc/php/8.2/apache2/conf.d/00-ioncube.ini >/dev/null
  fi

  if [ -d /etc/php/8.2/fpm/conf.d ]; then
    echo "zend_extension=$php_ext_dir/$loader_file" | sudo tee /etc/php/8.2/fpm/conf.d/00-ioncube.ini >/dev/null
  fi
}

info() {
  printf "%b[INFO]%b %s\n" "$C_BLUE" "$C_RESET" "$*"
}

ok() {
  printf "%b[OK]%b %s\n" "$C_GREEN" "$C_RESET" "$*"
}

warn() {
  printf "%b[WARN]%b %s\n" "$C_YELLOW" "$C_RESET" "$*"
}

fatal() {
  printf "%b[ERRO]%b %s\n" "$C_RED" "$C_RESET" "$*"
  exit 1
}

to_lower() {
  printf '%s' "$1" | tr '[:upper:]' '[:lower:]'
}

is_yes() {
  case "$(to_lower "${1:-}")" in
    s|sim|y|yes)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

ask_default() {
  local var_name="$1"
  local label="$2"
  local default_value="$3"
  local current_value="${!var_name:-}"
  local answer=""

  if [ -n "$current_value" ]; then
    return
  fi

  read -r -p "${label} [${default_value}]: " answer
  if [ -z "$answer" ]; then
    answer="$default_value"
  fi
  printf -v "$var_name" '%s' "$answer"
}

normalize_access_mode() {
  case "$(to_lower "$1")" in
    1|dominio|domain)
      printf '%s' "domain"
      ;;
    3|publico|public|publicip|ippublico)
      printf '%s' "public"
      ;;
    *)
      printf '%s' "local"
      ;;
  esac
}

detect_local_ip() {
  local result=""
  result=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i=1; i<=NF; i++) if ($i == "src") { print $(i+1); exit }}' || true)
  if [ -z "$result" ]; then
    result=$(hostname -I 2>/dev/null | awk '{print $1}' || true)
  fi
  if [ -z "$result" ]; then
    result="127.0.0.1"
  fi
  printf '%s' "$result"
}

detect_public_ip() {
  local result=""
  result=$(curl -fsS https://api.ipify.org 2>/dev/null || true)
  if [ -z "$result" ]; then
    result=$(curl -fsS https://ifconfig.me 2>/dev/null || true)
  fi
  printf '%s' "$result"
}

set_env() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    echo "${key}=${value}" >> .env
  fi
}

line
info "Instalador Gestor Veet - Ubuntu 22.04"
line

if ! command -v lsb_release >/dev/null 2>&1; then
  info "Instalando lsb-release"
  apt_update
  apt_install lsb-release
fi

UBU=$(lsb_release -rs)
info "Ubuntu detectado: ${UBU}"
if [ "${UBU}" != "22.04" ]; then
  warn "Este instalador foi preparado para Ubuntu 22.04."
fi

line
info "Etapa 1/8: instalando dependencias de sistema"
echo 'Acquire::ForceIPv4 "true";' | sudo tee /etc/apt/apt.conf.d/99force-ipv4 >/dev/null
apt_update
apt_install git unzip curl ca-certificates software-properties-common build-essential
sudo add-apt-repository ppa:ondrej/php -y
apt_update
apt_install php8.2 php8.2-cli libapache2-mod-php8.2 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-mysql php8.2-bcmath php8.2-intl php8.2-gd php8.2-soap php8.2-readline php8.2-redis
apt_install php8.2-ioncube-loader || apt_install php-ioncube-loader || true
apt_install mysql-server apache2

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl enable --now mysql apache2
else
  sudo service mysql start
  sudo service apache2 start
fi
ok "Dependencias instaladas"

if ! php -m | grep -qi ioncube; then
  warn "Pacote ionCube nao encontrado no apt. Tentando instalacao manual..."
  install_ioncube_manual || fatal "Falha ao instalar ionCube Loader manualmente."
fi

if ! php -m | grep -qi ioncube; then
  fatal "ionCube Loader nao carregado no PHP CLI."
fi

line
info "Etapa 2/8: configurando Composer e Node.js"
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

NEED_NODE_SETUP="false"
if ! command -v node >/dev/null 2>&1; then
  NEED_NODE_SETUP="true"
else
  NODE_MAJOR=$(node -v | sed -E 's/^v([0-9]+).*/\1/')
  if [ "${NODE_MAJOR}" -lt 20 ]; then
    warn "Node atual v${NODE_MAJOR}. Atualizando para Node 20 LTS."
    NEED_NODE_SETUP="true"
  fi
fi

if [ "$NEED_NODE_SETUP" = "true" ]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  apt_install nodejs
fi

if ! command -v npm >/dev/null 2>&1; then
  apt_install npm
fi
ok "Composer e Node.js prontos"

line
info "Etapa 3/8: configurando Apache + PHP"
sudo a2dismod php8.1 >/dev/null 2>&1 || true
sudo a2enmod php8.2 rewrite
sudo systemctl restart apache2
ok "Apache configurado"

line
info "Etapa 4/8: configuracao de acesso"
LOCAL_IP=$(detect_local_ip)
PUBLIC_IP=$(detect_public_ip)

ACCESS_MODE_INPUT=${ACCESS_MODE:-}
DOMAIN=${DOMAIN:-}

if [ -z "$ACCESS_MODE_INPUT" ]; then
  echo "Escolha o tipo de acesso inicial:"
  echo "  1) Dominio"
  echo "  2) IP local da VM (padrao: ${LOCAL_IP})"
  if [ -n "$PUBLIC_IP" ]; then
    echo "  3) IP publico da VM (${PUBLIC_IP})"
  else
    echo "  3) IP publico da VM (nao detectado, cai para IP local)"
  fi
  read -r -p "Opcao [2]: " ACCESS_MODE_INPUT
fi

ACCESS_MODE_RESOLVED=$(normalize_access_mode "${ACCESS_MODE_INPUT:-2}")
SERVER_NAME="$LOCAL_IP"
APP_URL="http://${LOCAL_IP}"

case "$ACCESS_MODE_RESOLVED" in
  domain)
    if [ -z "$DOMAIN" ]; then
      read -r -p "Informe o dominio (exemplo: app.seudominio.com): " DOMAIN
    fi
    if [ -n "$DOMAIN" ]; then
      SERVER_NAME="$DOMAIN"
      APP_URL="http://${DOMAIN}"
      ok "Acesso por dominio: ${DOMAIN}"
    else
      warn "Dominio nao informado. Usando IP local: ${LOCAL_IP}"
      ACCESS_MODE_RESOLVED="local"
    fi
    ;;
  public)
    if [ -n "$PUBLIC_IP" ]; then
      SERVER_NAME="$PUBLIC_IP"
      APP_URL="http://${PUBLIC_IP}"
      ok "Acesso por IP publico: ${PUBLIC_IP}"
    else
      warn "IP publico nao detectado. Usando IP local: ${LOCAL_IP}"
      ACCESS_MODE_RESOLVED="local"
    fi
    ;;
  *)
    ok "Acesso por IP local: ${LOCAL_IP}"
    ;;
esac

line
info "Etapa 5/8: configuracao do banco"
ask_default DB_NAME "Nome do banco (DB_NAME)" "${DB_NAME:-gestorvet}"
ask_default DB_USER "Usuario do banco (DB_USER)" "${DB_USER:-gestorvet}"
ask_default DB_PASS "Senha do banco (DB_PASS)" "${DB_PASS:-gestorvet}"

if printf '%s' "$DB_PASS" | grep -q "'"; then
  fatal "A senha do banco nao pode conter aspas simples (')."
fi

sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
ok "Banco configurado"

line
info "Etapa 6/8: preparando .env e dependencias do projeto"
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
  else
    touch .env
  fi
fi

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
    npm ci --legacy-peer-deps
  else
    npm install --legacy-peer-deps
  fi
fi
ok "Dependencias do projeto instaladas"

line
info "Etapa 7/8: chave, build e migrations"
if [ -f artisan ]; then
  php artisan key:generate
  php artisan migrate
fi

if [ -f package.json ]; then
  npm run dev
fi
ok "Aplicacao preparada"

line
info "Etapa 8/8: virtual host Apache"
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
ok "Virtual host aplicado"

if [ "$ACCESS_MODE_RESOLVED" = "domain" ]; then
  INSTALL_SSL=${INSTALL_SSL:-}
  if [ -z "$INSTALL_SSL" ]; then
    read -r -p "Deseja instalar SSL LetsEncrypt agora? (s/N): " INSTALL_SSL
  fi

  if is_yes "$INSTALL_SSL"; then
    apt_install certbot python3-certbot-apache
    CERT_EMAIL=${CERT_EMAIL:-}
    if [ -z "$CERT_EMAIL" ]; then
      read -r -p "Email do certificado (opcional): " CERT_EMAIL
    fi

    if [ -n "$CERT_EMAIL" ]; then
      sudo certbot --apache -d "$DOMAIN" --agree-tos -m "$CERT_EMAIL" --redirect
    else
      sudo certbot --apache -d "$DOMAIN" --agree-tos --register-unsafely-without-email --redirect
    fi
    ok "SSL configurado"
  fi
else
  warn "SSL nao pode ser emitido para IP. Use dominio para habilitar LetsEncrypt."
fi

line
ok "Instalacao concluida"
echo "URL: ${APP_URL}"
echo "ServerName Apache: ${SERVER_NAME}"
echo "Banco: ${DB_NAME} (usuario ${DB_USER})"
line
