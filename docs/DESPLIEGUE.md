# Despliegue

La URL pública de la demo es **https://demo-llamada.navertia.com** (producción en el VPS, detrás de su Traefik).
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

La demo corre en el VPS compartido detrás de su **Traefik** (repo `srv1576694.hstgr.cloud`), que es el único
proceso que escucha en 80/443 y el único que gestiona los certificados de Let's Encrypt. Este stack no publica
puertos ni trae reverse proxy propio: `php` y `bot` se unen a la red externa `web` y se anuncian con labels de
Traefik (mismo patrón que el bot de voz y la agenda del VPS).

Un solo dominio, `APP_DOMAIN=demo-llamada.navertia.com`:

| Ruta pública | Router Traefik | Destino |
|--------------|----------------|---------|
| `/ws` (wss), `/twilio/*` | `demo-llamada-bot` (prioridad 100) | `bot:7860` |
| resto | `demo-llamada-php` | `php:8080` |

Ambos routers usan `entrypoints=websecure`, `tls.certresolver=le` y el middleware `demo-llamada-secheaders`
(HSTS con subdominios, `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`). Routers,
servicios y middleware llevan el prefijo `demo-llamada-` para no chocar con los de otros stacks del VPS.
`traefik.docker.network=web` es obligatorio porque `php` y `bot` están en dos redes; sin ella Traefik puede
elegir la IP de `backend` y devolver 504.

`POST /dial-out` del bot **no** se publica: lo llama PHP por la red interna.

Redes: `web` (externa, compartida con Traefik y los demás stacks; también da salida a Internet al bot) y
`backend` (interna: `php`, `bot`, `migrate`, `db`). Como en `web` hay otros servicios llamados `php` (la agenda),
los servicios se hablan por alias únicos en `backend` y el compose fija `DB_HOST=demo-llamada-db`,
`PHP_API_BASE_URL=http://demo-llamada-php:8080` y `BOT_BASE_URL=http://demo-llamada-bot:7860`, que pisan los del
`.env`.

Mejoras frente al despliegue de referencia: construcciones *multi-stage* (Composer y Tailwind fuera de la imagen
final, sin Node ni herramientas de desarrollo), **todos los procesos sin root** (`www-data` en Apache :8080,
`bot` uid 10001), sistema de ficheros de solo lectura, `cap_drop: ALL`, `no-new-privileges`, *healthchecks* en
todos los servicios, `restart: unless-stopped`, logs con rotación, MariaDB en una red interna **sin puertos
publicados**, migraciones automáticas (`migrate` se ejecuta antes de `php`) y **sin phpMyAdmin**.

### Pasos

1. Traefik del VPS arriba (crea la red `web`, `docker network create web`, una vez por máquina).
2. DNS: registro A de `demo-llamada.navertia.com` al VPS, ya propagado (`dig +short demo-llamada.navertia.com`);
   sin él Traefik falla el reto TLS-ALPN y gasta cuota de Let's Encrypt.
3. `.env` de producción: `APP_DOMAIN=demo-llamada.navertia.com`,
   `PUBLIC_BASE_URL=https://demo-llamada.navertia.com`, contraseñas de BD
   fuertes, `INTERNAL_API_TOKEN` (`openssl rand -hex 32`), claves de Twilio / Deepgram / ElevenLabs / LLM,
   `HANDOFF_PHONE_NUMBER`, `APP_ENV=production`.
4. `make prod-up` (equivale a `docker compose -f docker-compose.prod.yml --env-file .env up -d --build --wait`).
5. Crear el primer administrador con **`make prod-admin`**. El comando es interactivo: pide email, nombre y contraseña (12-24
   caracteres, sin eco, confirmada dos veces) y la envía por stdin al script `calendar/src/scripts/create-admin.php`
   dentro del contenedor `php`; nunca pasa por argumentos ni por el historial. Es idempotente: si el email ya existe,
   actualiza nombre, rol (`admin`) y contraseña en lugar de duplicar. En desarrollo existe `make admin`.
   El número de traspaso inicial sale de `HANDOFF_PHONE_NUMBER`; después se edita en el panel (*Ajustes → Traspaso*),
   ver [CALENDARIO.md](CALENDARIO.md).
6. Sembrar los datos mínimos con **`make prod-seed`** (tiendas, horarios, servicio, ajustes y comerciales; ver
   [Datos iniciales](#datos-iniciales-make-prod-seed)). **Sin este paso el bot no puede reservar**: `GET /mcp/stores`
   devuelve una lista vacía y el asistente acaba pasando la llamada a un asesor. Comprobación:
   `docker compose -p navertia-prod exec db sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" -e "select count(*) from stores; select count(*) from commercials"'`
   debe dar 3 y 4.
7. En Twilio: webhook de voz del número -> `https://demo-llamada.navertia.com/twilio/voice` (POST).

### Datos iniciales (`make prod-seed`)

`make prod-up` solo aplica migraciones: la base de producción arranca **vacía** (sin tiendas, comerciales,
servicio ni ajustes). `make prod-seed` ejecuta, en este orden y dentro del contenedor `migrate`, solo los seeders
seguros para producción:

| Seeder | Qué crea |
|--------|----------|
| `StoresSeeder` | 3 tiendas de demo (ids 1-3) y sus horarios |
| `ServicesSeeder` | el servicio "Cita comercial" (30 min) |
| `SettingsSeeder` | intervalo y duración de cita; número de traspaso inicial desde `HANDOFF_PHONE_NUMBER` (no pisa el editado en el panel) |
| `ApiKeysSeeder` | registra `INTERNAL_API_TOKEN` en `api_keys` (auditable) |
| `ProdCommercialsSeeder` | los 4 comerciales de demo con su tienda y horario semanal |

Es idempotente: se puede relanzar tras cada despliegue sin duplicar nada. `ProdCommercialsSeeder` identifica a
cada comercial por **email** (no por id) y les pone una contraseña **aleatoria que no se guarda**: sirven para
recibir citas, no para entrar; si alguno necesita acceso, un admin le pone contraseña desde *Usuarios*.

**Nunca** ejecutes `make seed` ni `phinx seed:run` sin `-s` en producción:

- `StaffSeeder` usa ids fijos 1-6: **sobrescribiría los admins reales** creados con `make prod-admin` (ids 1 y 2) y
  crea cuentas con la contraseña pública de [CREDENTIALS.md](CREDENTIALS.md).
- `ClientsSeeder` y `DemoActivitySeeder` meten clientes, citas, una llamada y un lead ficticios.

`make seed` además usa el compose de desarrollo, así que en el VPS no llega al stack `navertia-prod`. Con
`seed:run -s` Phinx ejecuta solo los seeders indicados, sin resolver dependencias; por eso `StaffSeeder` nunca se
lanza desde `prod-seed`. Si las tiendas se crean a mano en el panel en lugar de con este comando, `StoresSeeder`
(ids 1-3) sobrescribiría las que ocupen esos ids.

Notas: las sesiones PHP viven en un `tmpfs` (se pierden al reiniciar el contenedor `php`); `WEBRTC` se desactiva
(`ENABLE_WEBRTC=false`).
