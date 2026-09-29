# Despliegue

La URL pública de la demo es **https://demo-llamada.navertia.com** (despliegue de producción con Caddy).
El túnel (`make tunnel`) es **solo para pruebas en local**; nunca es la URL pública.

## Desarrollo

```bash
cp .env.example .env        # o make env; rellena secretos
make up                     # composer install + Tailwind + docker compose up --build --wait
make migrate seed           # esquema y datos de demo
```

`make up` levanta `php` (8090), `db` (3316), `phpmyadmin` (8091) y `bot` (7860). Comandos en `make help`.

## Probar Twilio en local: `make tunnel` (solo testing)

Twilio necesita una URL pública HTTPS. Para probar desde una máquina de desarrollo, sin tocar el despliegue real,
`make tunnel` ejecuta `scripts/tunnel.sh`, que:

1. arranca **cloudflared** (sin cuenta) en un contenedor conectado a la red del compose, apuntando a `bot:7860`
   (`TUNNEL=ngrok make tunnel` usa ngrok; requiere `NGROK_AUTHTOKEN`);
2. lee la URL pública (`https://….trycloudflare.com`), la escribe en `PUBLIC_BASE_URL` del `.env`;
3. recrea el bot para que la use y muestra el webhook a configurar: `POST {URL}/twilio/voice`.

Después, en *Dial Out* la llamada saliente ya usa `wss://…/ws` alcanzable por Twilio. Ctrl+C cierra el túnel.
La URL `trycloudflare.com` es temporal (cambia en cada ejecución): no la uses como webhook del número de la demo,
que debe seguir apuntando a `https://demo-llamada.navertia.com/twilio/voice`.
Cuidado: las llamadas reales **cuestan dinero**; las pruebas automáticas usan siempre un bot simulado.

## Producción (`docker-compose.prod.yml`)

Un solo dominio, `APP_DOMAIN=demo-llamada.navertia.com`, con TLS automático de Let's Encrypt (Caddy):

| Ruta pública | Destino |
|--------------|---------|
| `/ws` (wss), `/twilio/*` | `bot:7860` |
| resto | `php:8080` |

`POST /dial-out` del bot **no** se publica: lo llama PHP por la red interna.

Mejoras frente al despliegue de referencia: construcciones *multi-stage* (Composer y Tailwind fuera de la imagen
final, sin Node ni herramientas de desarrollo), **todos los procesos sin root** (`www-data` en Apache :8080,
`bot` uid 10001, `caddy` uid 10002 con solo `NET_BIND_SERVICE`), sistema de ficheros de solo lectura,
`cap_drop: ALL`, `no-new-privileges`, *healthchecks* en todos los servicios, `restart: unless-stopped`, logs con
rotación, MariaDB en una red interna **sin puertos publicados**, migraciones automáticas (`migrate` se ejecuta
antes de `php`) y **sin phpMyAdmin**. Cabeceras de seguridad (HSTS, `nosniff`, `X-Frame-Options`, etc.) desde Caddy.

### Pasos

1. DNS: registro A/AAAA de `demo-llamada.navertia.com` al servidor; puertos 80 y 443 abiertos.
2. `.env` de producción: `APP_DOMAIN=demo-llamada.navertia.com`, `ACME_EMAIL`,
   `PUBLIC_BASE_URL=https://demo-llamada.navertia.com`, contraseñas de BD
   fuertes, `INTERNAL_API_TOKEN` (`openssl rand -hex 32`), claves de Twilio / Deepgram / ElevenLabs / LLM,
   `HANDOFF_PHONE_NUMBER`, `APP_ENV=production`.
3. `make prod-up` (equivale a `docker compose -f docker-compose.prod.yml --env-file .env up -d --build --wait`).
4. Crear el primer administrador con **`make prod-admin`**. Los seeders de desarrollo **no** se ejecutan en
   producción (traen una contraseña pública). El comando es interactivo: pide email, nombre y contraseña (12-24
   caracteres, sin eco, confirmada dos veces) y la envía por stdin al script `calendar/src/scripts/create-admin.php`
   dentro del contenedor `php`; nunca pasa por argumentos ni por el historial. Es idempotente: si el email ya existe,
   actualiza nombre, rol (`admin`) y contraseña en lugar de duplicar. En desarrollo existe `make admin`.
   El número de traspaso inicial sale de `HANDOFF_PHONE_NUMBER`; después se edita en el panel (*Ajustes → Traspaso*),
   ver [CALENDARIO.md](CALENDARIO.md).
5. En Twilio: webhook de voz del número -> `https://demo-llamada.navertia.com/twilio/voice` (POST).

Notas: las sesiones PHP viven en un `tmpfs` (se pierden al reiniciar el contenedor `php`); `WEBRTC` se desactiva
(`ENABLE_WEBRTC=false`); para probar prod en una máquina con 80/443 ocupados se pueden definir `HTTP_PORT`/`HTTPS_PORT`
y `APP_DOMAIN=localhost` (certificado interno de Caddy).
