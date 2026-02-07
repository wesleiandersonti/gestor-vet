# Estrutura do Projeto

Este documento descreve a estrutura tecnica do `gestor-vet` para facilitar manutencao, onboarding e evolucao do sistema.

## Visao geral de pastas

```text
gestor-vet/
|- app/                    # Dominio da aplicacao (controllers, models, services, middleware)
|- bootstrap/              # Bootstrap do Laravel
|- config/                 # Configuracoes da aplicacao
|- database/               # Migrations, seeders e factories
|- deploy/                 # Artefatos de deploy (ex.: supervisor)
|- docker/                 # Arquivos auxiliares de container
|- lang/                   # Traducoes
|- public/                 # Documento publico (entrypoint web + assets gerados)
|- resources/              # Blade, assets de frontend e menus
|- routes/                 # Rotas web/api/console/channels
|- scripts/                # Instalacao, update e operacao em VM
|- storage/                # Runtime (cache, logs, sessions, app files)
|- tests/                  # Testes automatizados
|- .github/workflows/      # CI no GitHub Actions
|- composer.json           # Dependencias PHP
|- package.json            # Dependencias Node
|- webpack.mix.js          # Pipeline de build de assets
```

## Mapa backend

- `app/Http/Controllers`
  - Controllers por dominio (clientes, campanhas, planos, pagamentos, conexoes, update)
  - Entradas HTTP principais da regra de negocio
- `app/Models`
  - Entidades Eloquent (Cliente, Plano, Pagamento, Campanha, User, Role etc.)
- `app/Console/Commands`
  - Jobs operacionais via artisan (`clientes:verificar-vencidos`, `campanhas:disparar`)
- `app/Http/Middleware`
  - Auth, permissao, role e guardas tecnicos (`technical.guard`)
- `app/Providers`
  - Registro de servicos e bootstrap de integracoes (ex.: Mercado Pago)
- `app/Services`
  - Servicos de dominio e modulos encapsulados
  - Existem arquivos `.pix` protegidos (nao editar sem pipeline interno)

## Mapa frontend

- `resources/views`
  - Templates Blade (layouts, modulos de negocio e paginas administrativas)
- `resources/assets`
  - Vendor assets, libs JS/CSS/SCSS e scripts de dashboard
- `resources/js` e `resources/css`
  - Entradas principais da aplicacao
- `webpack.mix.js`
  - Compilacao/minificacao de assets para `public/assets`

## Rotas e pontos de entrada

- `routes/web.php`
  - Fluxo principal do sistema, dashboard, modulos e rotas tecnicas
- `routes/api.php`
  - Endpoints API
- `routes/console.php`
  - Definicoes de comandos de console
- `routes/channels.php`
  - Canais de broadcast

## Operacao e deploy

- `scripts/install-ubuntu.sh`
  - Provisionamento completo em Ubuntu 22.04
- `scripts/update-ubuntu.sh`
  - Update de codigo + dependencias + build + migrate
- `scripts/update-gestor.sh`
  - Descoberta automatica da pasta do projeto para update
- `scripts/install-supervisor-queue.sh`
  - Configura worker de fila com Supervisor
- `deploy/supervisor/gestor-veet-worker.conf`
  - Arquivo de referencia para processos de queue

## Dados e runtime

- `storage/`
  - Conteudo de runtime (nao tratar como codigo-fonte)
- `.env` / `.env.example`
  - Configuracao de ambiente e segredos

## Convencao para evolucao

- Nova funcionalidade de negocio
  1. Model + migration
  2. Controller + Request/Service
  3. Route em `routes/web.php` ou `routes/api.php`
  4. View Blade em `resources/views/...`
  5. Ajustes de permissao/role quando necessario
- Ajuste de infra/deploy
  1. Atualizar scripts em `scripts/`
  2. Atualizar README e este documento
  3. Validar CI em `.github/workflows/ci.yml`
