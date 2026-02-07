#!/usr/bin/env bash
#
# Preparador de VM Ubuntu 22.04 para Gestor Veet
# Script interativo e moderno para preparacao completa do ambiente
# Uso: bash scripts/prepare-vm.sh
#

set -euo pipefail

# Cores para output
C_RESET="\033[0m"
C_BLUE="\033[1;34m"
C_GREEN="\033[1;32m"
C_YELLOW="\033[1;33m"
C_RED="\033[1;31m"
C_CYAN="\033[1;36m"
C_MAGENTA="\033[1;35m"

# Funcoes de output
print_banner() {
    clear
    echo -e "${C_CYAN}"
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║                                                            ║"
    echo "║        🚀 PREPARADOR DE VM - GESTOR VEET                   ║"
    echo "║        Ubuntu 22.04 LTS - Ambiente Completo                ║"
    echo "║                                                            ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo -e "${C_RESET}"
    echo
}

info() {
    echo -e "${C_BLUE}[INFO]${C_RESET} $1"
}

success() {
    echo -e "${C_GREEN}[OK]${C_RESET} $1"
}

warn() {
    echo -e "${C_YELLOW}[AVISO]${C_RESET} $1"
}

error() {
    echo -e "${C_RED}[ERRO]${C_RESET} $1"
}

step() {
    echo
    echo -e "${C_MAGENTA}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${C_RESET}"
    echo -e "${C_MAGENTA}  📌 $1${C_RESET}"
    echo -e "${C_MAGENTA}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${C_RESET}"
}

ask() {
    local prompt="$1"
    local response
    read -r -p "$(echo -e "${C_YELLOW}[PERGUNTA]${C_RESET} $prompt (s/n): ")" response
    case "$response" in
        [SsYy]* ) return 0 ;;
        * ) return 1 ;;
    esac
}

spinner() {
    local pid=$1
    local delay=0.1
    local spinstr='|/-\'
    while [ -d /proc/$pid ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# Verificar se eh root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "Este script precisa ser executado como root!"
        error "Use: sudo bash scripts/prepare-vm.sh"
        exit 1
    fi
    success "Executando como root"
}

# Verificar versao do Ubuntu
check_ubuntu_version() {
    step "Verificando Sistema Operacional"
    
    if ! command -v lsb_release &> /dev/null; then
        apt-get install -y lsb-release &> /dev/null
    fi
    
    local version=$(lsb_release -rs)
    local codename=$(lsb_release -cs)
    
    info "Versao detectada: Ubuntu $version ($codename)"
    
    if [[ "$version" != "22.04" ]]; then
        warn "Este script foi otimizado para Ubuntu 22.04 LTS"
        warn "Versao atual: $version"
        
        if ! ask "Deseja continuar mesmo assim?"; then
            error "Instalacao cancelada pelo usuario"
            exit 1
        fi
    else
        success "Ubuntu 22.04 LTS confirmado"
    fi
}

# Configurar timezone
setup_timezone() {
    step "Configurando Timezone"
    
    info "Timezone atual: $(timedatectl | grep "Time zone" | awk '{print $3}')"
    
    if ask "Deseja definir timezone para America/Sao_Paulo?"; then
        timedatectl set-timezone America/Sao_Paulo
        success "Timezone configurado: America/Sao_Paulo"
    fi
}

# Instalar QEMU Guest Agent
setup_qemu() {
    step "Instalando QEMU Guest Agent"
    
    if systemctl is-active --quiet qemu-guest-agent 2>/dev/null; then
        success "QEMU Guest Agent ja esta instalado e rodando"
        return
    fi
    
    info "Instalando pacote qemu-guest-agent..."
    apt-get install -y qemu-guest-agent &> /dev/null &
    spinner $!
    
    systemctl enable qemu-guest-agent &> /dev/null
    systemctl start qemu-guest-agent &> /dev/null
    
    if systemctl is-active --quiet qemu-guest-agent; then
        success "QEMU Guest Agent instalado e ativo"
    else
        warn "QEMU Guest Agent instalado mas pode nao estar ativo nesta VM"
    fi
}

# Atualizar sistema
update_system() {
    step "Atualizando Sistema"
    
    info "Atualizando lista de pacotes..."
    apt-get update &> /dev/null &
    spinner $!
    
    info "Atualizando pacotes instalados..."
    DEBIAN_FRONTEND=noninteractive apt-get upgrade -y &> /dev/null &
    spinner $!
    
    info "Removendo pacotes desnecessarios..."
    apt-get autoremove -y &> /dev/null &
    apt-get autoclean &> /dev/null &
    
    success "Sistema atualizado"
}

# Instalar dependencias basicas
install_base_deps() {
    step "Instalando Dependencias Basicas"
    
    local packages="software-properties-common curl wget git unzip apt-transport-https ca-certificates gnupg2 build-essential net-tools"
    
    info "Instalando: $packages"
    apt-get install -y $packages &> /dev/null &
    spinner $!
    
    success "Dependencias basicas instaladas"
}

# Instalar Apache
install_apache() {
    step "Instalando Apache 2"
    
    if command -v apache2 &> /dev/null; then
        success "Apache ja esta instalado: $(apache2 -v | head -n1)"
    else
        info "Instalando Apache 2..."
        apt-get install -y apache2 &> /dev/null &
        spinner $!
        
        a2enmod rewrite &> /dev/null
        systemctl enable apache2 &> /dev/null
        systemctl start apache2
        
        success "Apache 2 instalado e ativo"
    fi
}

# Instalar MySQL
install_mysql() {
    step "Instalando MySQL Server"
    
    if command -v mysql &> /dev/null; then
        success "MySQL ja esta instalado: $(mysql --version | head -n1)"
    else
        info "Instalando MySQL Server..."
        apt-get install -y mysql-server &> /dev/null &
        spinner $!
        
        systemctl enable mysql &> /dev/null
        systemctl start mysql
        
        success "MySQL Server instalado e ativo"
    fi
}

# Instalar PHP 8.2
install_php() {
    step "Instalando PHP 8.2"
    
    if command -v php &> /dev/null && php -v | grep -q "PHP 8.2"; then
        success "PHP 8.2 ja esta instalado: $(php -v | head -n1)"
        return
    fi
    
    info "Adicionando repositorio Ondrej PHP..."
    add-apt-repository -y ppa:ondrej/php &> /dev/null &
    spinner $!
    
    apt-get update &> /dev/null &
    spinner $!
    
    info "Instalando PHP 8.2 e extensoes..."
    local php_packages="php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath php8.2-intl php8.2-soap php8.2-readline php8.2-redis libapache2-mod-php8.2"
    
    apt-get install -y $php_packages &> /dev/null &
    spinner $!
    
    success "PHP 8.2 instalado com sucesso"
    php -v | head -n1
}

# Instalar ionCube Loader
install_ioncube() {
    step "Instalando ionCube Loader"
    
    if php -m | grep -qi ioncube; then
        success "ionCube Loader ja esta ativo"
        return
    fi
    
    info "Baixando ionCube Loader..."
    cd /tmp
    wget -q https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
    
    info "Extraindo arquivos..."
    tar -xzf ioncube_loaders_lin_x86-64.tar.gz
    
    info "Instalando loader para PHP 8.2..."
    cp ioncube/ioncube_loader_lin_8.2.so /usr/lib/php/20220829/
    
    echo "zend_extension=/usr/lib/php/20220829/ioncube_loader_lin_8.2.so" > /etc/php/8.2/mods-available/ioncube.ini
    phpenmod ioncube
    
    # Reiniciar Apache para carregar o modulo
    systemctl restart apache2
    
    if php -m | grep -qi ioncube; then
        success "ionCube Loader instalado e ativo"
    else
        error "Falha ao ativar ionCube Loader"
        error "Verifique manualmente com: php -m | grep ioncube"
    fi
    
    cd - > /dev/null
}

# Instalar Node.js 20
install_node() {
    step "Instalando Node.js 20 LTS"
    
    if command -v node &> /dev/null && node -v | grep -q "v20"; then
        success "Node.js 20 ja esta instalado: $(node -v)"
        return
    fi
    
    info "Configurando repositorio NodeSource..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - &> /dev/null &
    spinner $!
    
    info "Instalando Node.js..."
    apt-get install -y nodejs &> /dev/null &
    spinner $!
    
    success "Node.js instalado: $(node -v)"
    success "NPM instalado: $(npm -v)"
}

# Instalar Composer
install_composer() {
    step "Instalando Composer"
    
    if command -v composer &> /dev/null; then
        success "Composer ja esta instalado: $(composer --version | head -n1)"
        return
    fi
    
    info "Baixando instalador do Composer..."
    curl -sS https://getcomposer.org/installer -o composer-setup.php
    
    info "Instalando..."
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer &> /dev/null
    rm composer-setup.php
    
    success "Composer instalado: $(composer --version | head -n1)"
}

# Configurar banco de dados inicial
setup_database() {
    step "Configurando Banco de Dados"
    
    info "Criando banco de dados 'gestorvet'..."
    
    mysql -e "CREATE DATABASE IF NOT EXISTS gestorvet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
        warn "Nao foi possivel criar banco automaticamente"
        warn "Voce precisara configurar o banco manualmente"
        return
    }
    
    mysql -e "CREATE USER IF NOT EXISTS 'gestorvet'@'localhost' IDENTIFIED BY 'gestorvet';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON gestorvet.* TO 'gestorvet'@'localhost';" 2>/dev/null
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null
    
    success "Banco de dados 'gestorvet' configurado"
    info "Usuario: gestorvet | Senha: gestorvet"
}

# Relatorio final
final_report() {
    step "Relatorio de Instalacao"
    
    echo
    echo -e "${C_GREEN}✅ VM PREPARADA COM SUCESSO!${C_RESET}"
    echo
    echo -e "${C_CYAN}Versoes Instaladas:${C_RESET}"
    echo "  • $(lsb_release -d | cut -f2)"
    echo "  • $(php -v | head -n1)"
    echo "  • $(apache2 -v | head -n1)"
    echo "  • $(mysql --version | head -n1)"
    echo "  • Node.js $(node -v)"
    echo "  • NPM $(npm -v)"
    echo "  • $(composer --version | head -n1)"
    echo "  • ionCube Loader: $(php -m | grep -i ioncube && echo 'Ativo' || echo 'Verificar')"
    echo
    echo -e "${C_CYAN}Proximos Passos:${C_RESET}"
    echo "  1. Clone o repositorio:"
    echo -e "     ${C_YELLOW}git clone https://github.com/wesleiandersonti/gestor-vet.git /var/www/gestor-vet${C_RESET}"
    echo
    echo "  2. Acesse o diretorio:"
    echo -e "     ${C_YELLOW}cd /var/www/gestor-vet${C_RESET}"
    echo
    echo "  3. Execute o instalador:"
    echo -e "     ${C_YELLOW}bash scripts/install-ubuntu.sh${C_RESET}"
    echo
    echo -e "${C_MAGENTA}Ou use o comando automatico completo:${C_RESET}"
    echo -e "${C_YELLOW}bash -lc 'set -euo pipefail; sudo apt-get update && sudo apt-get install -y git && rm -rf /var/www/gestor-vet && git clone --depth 1 https://github.com/wesleiandersonti/gestor-vet.git /var/www/gestor-vet && cd /var/www/gestor-vet && ACCESS_MODE=2 DB_NAME=gestorvet DB_USER=gestorvet DB_PASS=gestorvet bash scripts/install-ubuntu.sh'${C_RESET}"
    echo
    echo -e "${C_GREEN}🎉 Sua VM esta pronta para receber o Gestor Veet!${C_RESET}"
    echo
}

# Funcao principal
main() {
    print_banner
    check_root
    check_ubuntu_version
    
    echo
    echo -e "${C_CYAN}Este script vai preparar sua VM Ubuntu 22.04 com:${C_RESET}"
    echo "  ✓ Atualizacoes do sistema"
    echo "  ✓ QEMU Guest Agent"
    echo "  ✓ Apache 2"
    echo "  ✓ MySQL Server"
    echo "  ✓ PHP 8.2 + todas as extensoes"
    echo "  ✓ ionCube Loader"
    echo "  ✓ Node.js 20 LTS"
    echo "  ✓ Composer"
    echo "  ✓ Banco de dados inicial"
    echo
    
    if ! ask "Deseja continuar com a preparacao?"; then
        error "Preparacao cancelada pelo usuario"
        exit 1
    fi
    
    # Executar todas as etapas
    update_system
    install_base_deps
    setup_timezone
    setup_qemu
    install_apache
    install_mysql
    install_php
    install_ioncube
    install_node
    install_composer
    setup_database
    
    # Relatorio final
    final_report
}

# Executar se for chamado diretamente
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
