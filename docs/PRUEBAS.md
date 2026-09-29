# Pruebas

| Comando | Qué ejecuta |
|---------|-------------|
| `make test` | todo lo siguiente |
| `make test-php` | PHPUnit dentro del contenedor `php` (`calendar/src/tests/Unit`) |
| `make test-bot` | pytest del bot (`bot/tests`, con `uv`) |
| `make test-e2e` | Playwright en Docker (`calendar/tests/e2e`) |

## e2e (Playwright)

Corre en la imagen oficial `mcr.microsoft.com/playwright` (perfil `test` del compose) contra `php-e2e`, una copia del
panel cuyo `BOT_BASE_URL` apunta a `mockbot` (`calendar/tests/mock-bot.js`). El bot simulado responde a
`POST /dial-out` y empuja los estados (`ringing` → `in-progress` → transcripción → `completed`) a la API interna
de PHP, de modo que se prueba todo el circuito de Dial Out **sin bot real ni Twilio**. Requiere el stack levantado
con migraciones y seeds (`make up migrate seed`). Capturas en `calendar/tests/results/`.

Cubre: autenticación y roles, todas las vistas (sin errores 5xx ni de JS), alta de cliente, modo claro/oscuro y
paleta, Dial Out (prefijo por defecto, desplegable con banderas y búsqueda, validación E.164, estados en vivo,
enlace a transcripción, errores, CSRF) y la API interna (401, disponibilidad, cliente, cita, lead, transcripción,
estado de llamada).

## Bot (pytest)

Proveedores, Twilio y PHP simulados (`httpx.MockTransport`, `TwilioGateway` falso): fábrica de proveedores,
construcción del prompt, cada herramienta (incluido el traspaso con Twilio simulado y creación del lead),
`/dial-out` (token, E.164, TwiML), webhook entrante, relé de estado, firma de Twilio, configuración de inactividad
y `/health`. **Nunca se hace una llamada real.**
