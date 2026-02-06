# Gestor Vet

Sistema web para gestao de operacoes IPTV baseado em Laravel.

## Instalacao no Ubuntu (comando unico)

Rode na raiz do projeto:

```bash
bash scripts/install-ubuntu.sh
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
- Instala dependencias (PHP, Composer, Node.js, MySQL)
- Configura o banco e usuario local
- Prepara o `.env` e gera a chave da aplicacao
- Instala dependencias do projeto
- Compila assets
- Roda migrations

## Comandos uteis

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Licenca

Uso interno. Todos os direitos reservados.
