#!/usr/bin/env bash
set -euo pipefail

detect_dir() {
  for d in \
    "/var/www/gestor-vet" \
    "/root/gestor-vet" \
    "$HOME/gestor-vet"
  do
    if [ -f "$d/artisan" ]; then
      printf '%s' "$d"
      return 0
    fi
  done

  found=$(find /var/www /root /home -maxdepth 4 -type d -name "gestor-vet" 2>/dev/null | head -n 1 || true)
  if [ -n "$found" ] && [ -f "$found/artisan" ]; then
    printf '%s' "$found"
    return 0
  fi

  return 1
}

APP_DIR=${APP_DIR:-}

if [ -z "$APP_DIR" ]; then
  APP_DIR=$(detect_dir || true)
fi

if [ -z "$APP_DIR" ]; then
  echo "Projeto gestor-vet nao encontrado."
  echo "Use: APP_DIR=/caminho/do/projeto bash scripts/update-gestor.sh"
  exit 1
fi

echo "Atualizando em: $APP_DIR"
APP_DIR="$APP_DIR" bash "$APP_DIR/scripts/update-ubuntu.sh"
