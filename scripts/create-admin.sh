#!/usr/bin/env bash
# Crea o actualiza un administrador de forma interactiva.
#   ./scripts/create-admin.sh prod   # stack docker-compose.prod.yml
#   ./scripts/create-admin.sh dev    # stack de desarrollo
# La contrasena se lee con `read -s` (sin eco), se confirma dos veces y viaja
# por stdin hasta PHP: nunca en argv ni en el historial de la shell.
set -euo pipefail
cd "$(dirname "$0")/.."

case "${1:-prod}" in
  prod) DC=(docker compose -f docker-compose.prod.yml --env-file .env) ;;
  dev)  DC=(docker compose) ;;
  *) echo "Uso: $0 [prod|dev]" >&2; exit 1 ;;
esac

# Fallo temprano y claro si el stack no esta arriba.
"${DC[@]}" ps --status running --services 2>/dev/null | grep -qx php \
  || { echo "El servicio php no está en marcha ($1). Levanta el stack primero." >&2; exit 1; }

read -r -p "Email del administrador: " ADMIN_EMAIL
read -r -p "Nombre: " ADMIN_NAME
read -r -s -p "Contraseña (12 a 24 caracteres): " PW1; echo
read -r -s -p "Repite la contraseña: " PW2; echo
[ "$PW1" = "$PW2" ] || { echo "Las contraseñas no coinciden." >&2; exit 1; }
unset PW2

export ADMIN_EMAIL ADMIN_NAME
# printf es un builtin: la contraseña no aparece en la lista de procesos.
printf '%s\n' "$PW1" | "${DC[@]}" exec -T -e ADMIN_EMAIL -e ADMIN_NAME php php scripts/create-admin.php
unset PW1
