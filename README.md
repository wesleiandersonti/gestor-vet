# Gestor Veet

Plataforma web para gestao de operacoes IPTV, clientes, planos e campanhas. Construida em Laravel.

## Destaques

- Gestao de clientes, planos e revendas
- Campanhas e notificacoes
- Pagamentos e cobranca
- Painel administrativo e permissoes

## Stack

- Backend: Laravel 10, PHP 8.1+
- Frontend: Laravel Mix (Webpack)
- Banco: MySQL 8

## Instalacao no Ubuntu 22.04 (comando unico)

Rode na raiz do projeto:

```bash
bash scripts/install-ubuntu.sh
```

Ou tudo em uma linha (clonar + instalar):

```bash
bash -lc "git clone https://github.com/wesleiandersonti/gestor-vet.git && cd gestor-vet && bash scripts/install-ubuntu.sh"
```

Valores padrao usados pelo script (podem ser sobrescritos por variaveis de ambiente):

- `DB_NAME=gestorvet`
- `DB_USER=gestorvet`
- `DB_PASS=gestorvet`

Exemplo com valores customizados:

```bash
DB_NAME=meubanco DB_USER=meuusuario DB_PASS=minhasenha bash scripts/install-ubuntu.sh
```

## O que o script faz

- Detecta a versao do Ubuntu
- Instala dependencias (PHP, Composer, Node.js, MySQL, Apache)
- Configura o banco e usuario local
- Prepara o `.env` e gera a chave da aplicacao
- Instala dependencias do projeto
- Compila assets
- Roda migrations
- Configura o Apache apontando para dominio (ou IP se vazio)
- Oferece SSL LetsEncrypt quando houver dominio

## Variaveis de ambiente

Edite o `.env` conforme sua infraestrutura. Campos minimos:

```
APP_NAME=GestorVeet
APP_ENV=local
APP_KEY=
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestorvet
DB_USERNAME=gestorvet
DB_PASSWORD=gestorvet
```

## Comandos uteis

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Agendamentos (cron)

No servidor Linux, adicione:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

## Filas (queue)

```bash
php artisan queue:work
```

## Build para producao

```bash
npm run prod
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Atualizacao do sistema

Na VM, execute:

```bash
bash scripts/update-ubuntu.sh
```

Se voce nao souber o caminho do projeto, use o atualizador automatico:

```bash
bash -lc "git clone https://github.com/wesleiandersonti/gestor-vet.git /tmp/gestor-vet-update && bash /tmp/gestor-vet-update/scripts/update-gestor.sh"
```

## Licenca

Uso interno. Todos os direitos reservados.
