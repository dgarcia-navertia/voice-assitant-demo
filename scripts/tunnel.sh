#!/usr/bin/env bash
# Tunel publico HTTPS hacia el bot para probar Twilio en local.
#   ./scripts/tunnel.sh            # cloudflared (por defecto, sin cuenta)
#   TUNNEL=ngrok ./scripts/tunnel.sh   # ngrok (requiere NGROK_AUTHTOKEN)
#
# Arranca el tunel, detecta la URL publica, la escribe en PUBLIC_BASE_URL del
# .env y recrea el bot para que la use. Ctrl+C cierra el tunel.
set -euo pipefail

cd "$(dirname "$0")/.."
[ -f .env ] || { echo "Falta .env (cp .env.example .env)" >&2; exit 1; }

TUNNEL="${TUNNEL:-cloudflared}"
PROJECT="${COMPOSE_PROJECT_NAME:-navertia-voice-demo}"
NETWORK="${PROJECT}_default"
LOG="$(mktemp)"
NAME="navertia-tunnel"

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; rm -f "$LOG"; }
trap cleanup EXIT INT TERM

docker network inspect "$NETWORK" >/dev/null 2>&1 || {
  echo "La red $NETWORK no existe: arranca antes el stack con 'make up'." >&2; exit 1; }

docker rm -f "$NAME" >/dev/null 2>&1 || true
case "$TUNNEL" in
  cloudflared)
    docker run -d --name "$NAME" --network "$NETWORK" cloudflare/cloudflared:latest \
      tunnel --no-autoupdate --url http://bot:7860 >/dev/null
    PATTERN='https://[a-z0-9-]+\.trycloudflare\.com'
    ;;
  ngrok)
    : "${NGROK_AUTHTOKEN:?Define NGROK_AUTHTOKEN para usar ngrok}"
    docker run -d --name "$NAME" --network "$NETWORK" -e NGROK_AUTHTOKEN ngrok/ngrok:latest \
      http bot:7860 --log stdout >/dev/null
    PATTERN='https://[a-z0-9-]+\.(ngrok-free\.app|ngrok\.io|ngrok\.app)'
    ;;
  *) echo "TUNNEL debe ser cloudflared o ngrok" >&2; exit 1 ;;
esac

echo "Esperando la URL publica del tunel ($TUNNEL)..."
URL=""
for _ in $(seq 1 60); do
  docker logs "$NAME" >"$LOG" 2>&1 || true
  URL="$(grep -Eo "$PATTERN" "$LOG" | head -n1 || true)"
  [ -n "$URL" ] && break
  sleep 1
done
[ -n "$URL" ] || { echo "No se obtuvo URL del tunel. Log:" >&2; cat "$LOG" >&2; exit 1; }

if grep -q '^PUBLIC_BASE_URL=' .env; then
  sed -i.bak -E "s#^PUBLIC_BASE_URL=.*#PUBLIC_BASE_URL=${URL}#" .env && rm -f .env.bak
else
  echo "PUBLIC_BASE_URL=${URL}" >> .env
fi

docker compose up -d --force-recreate bot >/dev/null
cat <<MSG

Tunel activo:  ${URL}
  PUBLIC_BASE_URL actualizado en .env y bot recreado.
  Webhook entrante de Twilio (Voice, HTTP POST): ${URL}/twilio/voice
  Media stream:                                  wss://${URL#https://}/ws
Ctrl+C para cerrar el tunel (PUBLIC_BASE_URL queda apuntando a el).
MSG

docker logs -f "$NAME" >/dev/null 2>&1 || true
