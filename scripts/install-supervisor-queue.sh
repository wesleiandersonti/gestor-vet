#!/usr/bin/env bash
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/gestor-vet}
SUPERVISOR_CONF_SRC=${SUPERVISOR_CONF_SRC:-$APP_DIR/deploy/supervisor/gestor-veet-worker.conf}
SUPERVISOR_CONF_DST=${SUPERVISOR_CONF_DST:-/etc/supervisor/conf.d/gestor-veet-worker.conf}

if [ ! -f "$SUPERVISOR_CONF_SRC" ]; then
  echo "Arquivo de configuracao nao encontrado: $SUPERVISOR_CONF_SRC"
  exit 1
fi

sudo apt-get update
sudo apt-get install -y supervisor

sudo cp "$SUPERVISOR_CONF_SRC" "$SUPERVISOR_CONF_DST"
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart gestor-veet-worker:*

echo "Supervisor configurado com sucesso."
sudo supervisorctl status gestor-veet-worker:*
