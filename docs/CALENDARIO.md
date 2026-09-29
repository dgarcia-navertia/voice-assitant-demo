# Calendario / panel de administración (`calendar/`)

PHP 8.4 con MVC propio (sin framework), Apache, MariaDB, Phinx y Tailwind. Código en `calendar/src/`:

```
app/            Controllers/ Models/ Services/ Middlewares/ Views/ + Router, Auth, Env, Database
config/routes.php
db/migrations/  baseline completo + voice_demo_tables
db/seeds/       Settings, Services, Stores, Staff, Clients, ApiKeys, DemoActivity
public/         index.php, .htaccess, static/ (logo, banderas, JS; app.css se genera)
tests/Unit/     PHPUnit
../assets/      app.css (fuente) + tailwind.config.js
../tests/       Playwright e2e (+ mock-bot.js)
```

## Vistas

| Ruta | Descripción | Roles |
|------|-------------|-------|
| `/login`, `/logout` | Acceso | — |
| `/dashboard` | Agenda (lista / semana) | todos |
| `/appointments`, `/appointments/create`, `/appointments/{id}` | Citas | todos (acotado por rol) |
| `/clients` | Clientes (alta y búsqueda) | todos |
| `/dial-out` | **Dial Out** (llamada saliente) | todos |
| `/calls`, `/calls/{sid}` | Historial y transcripción | todos |
| `/leads` | Contactos derivados a comercial | todos |
| `/users`, `/stores`, `/holidays` | Ajustes | admin |
| `/commercials` (+ overrides) | Comerciales y horarios | admin, manager (su tienda) |
| `/account` | Cambio de contraseña | todos |

### Dial Out

Píldora de cristal con selector de prefijo (todas las banderas, buscable por país/prefijo/ISO, por defecto España +34),
campo de teléfono y botón *Dial*. La validación E.164 (`^\+[1-9]\d{6,14}$`) se hace en el navegador y de nuevo en
PHP (`App\Services\PhoneNumber`). `POST /dial-out` exige token CSRF y llama al bot (`App\Services\BotClient`).
El estado se consulta con `GET /dial-out/status/{sid}` (polling cada 1,5 s) hasta un estado final y, después, hasta
que aparece la transcripción (enlace *Ver transcripción*). Estados: `queued`, `ringing`, `in-progress`,
`completed`, `failed` (+ `busy`, `no-answer`, `canceled`). Un estado no terminal nunca pisa uno terminal.

Las banderas son SVG locales (`static/flags/`, del paquete MIT `flag-icons`) y los nombres salen de
`Intl.DisplayNames('es')`: no hay CDN.

## API interna (`/mcp/*`)

Autenticación: `Authorization: Bearer <INTERNAL_API_TOKEN>` o `X-API-Key`. También valen claves activas de
tipo `mcp` en `api_keys` (mismo patrón que la referencia). Sin token: `401 {"error":"UNAUTHORIZED"}`.

| Método y ruta | Cuerpo / query | Respuesta |
|---------------|----------------|-----------|
| `GET /mcp/stores`, `GET /mcp/stores/{id}` | — | tiendas con `schedule {mon_to_friday, saturday}` |
| `GET /mcp/availability` | `store_id`, `date=YYYY-MM-DD`, `service_id?` | `{date, slots:[{time, commercial_id, commercial_name}]}` |
| `GET /mcp/clients/by-phone` | `phone` | `{client}` o 404 |
| `POST /mcp/clients` | `client_name`, `client_phone`, `client_email?`, `client_type?` | 201 `{client}` |
| `POST /mcp/appointments` | `store_id`, `client_id`, `starts_at`, `service_id?`, `commercial_id?` | 201 cita · 409 sin hueco · 422 pasada |
| `POST /mcp/transcripts/batch` | `sid`, `turns[{role, transcript_text, turn_index, interrupted?}]` | 201 `{saved}` (idempotente por `sid`) |
| `POST /mcp/transcripts` | un turno | 201 |
| `POST /mcp/leads` | `phone`, `name?`, `store_id?`, `reason?`, `call_sid?`, `source?`, `transferred?` | 201 `{lead}` |
| `POST /mcp/calls/status` | `call_sid`, `status`, `to?`, `from?`, `direction?`, `duration?`, `error?` | `{call}` (upsert) |

La elección de comercial y la comprobación de hueco se repiten **dentro de la transacción** de reserva
(`AppointmentBookingService`, con bloqueo por tienda), lo que cierra la carrera entre consultar y reservar.
El contrato formal está en `openspec/changes/initial-build/specs/calendar/spec.md`.

## Estética y tema (liquid glass)

Definido en `calendar/assets/app.css` y `tailwind.config.js`. La paleta vive como **variables CSS** y como
**tokens de Tailwind**:

| Token | Valor | Uso |
|-------|-------|-----|
| `--primary` / `bg-primary` | `#075056` | botones, selección, marca |
| `--accent` / `bg-accent` | `#DC3225` | CTA (botón *Dial*) |
| `--ink` / `text-ink` | `#26180F` | texto y superficies oscuras |
| `--secondary` / `text-secondary` | `#6A959A` | apoyo, manchas de fondo |

Cristal: `.glass`, `.glass-strong`, `.glass-pop`, `.card` (translucidez, `backdrop-filter`, borde suave con brillo
interior); botones y campos en píldora (`.btn`, `.input`, `.pill`). Modo claro/oscuro con `[data-theme]` (sigue al
sistema, se puede forzar con el interruptor y se recuerda en `localStorage`); las escalas `gray`, `brand` y de
estado se invierten por variables, así las vistas heredadas funcionan en ambos modos.

Compilar: `make css` (Tailwind *standalone* en Docker, sin Node ni CDN). `app.css` compilado no se versiona.
El logotipo de Navertia se descargó de navertia.com (`static/navertia-logo-*.png`, versiones para fondo claro y oscuro).
