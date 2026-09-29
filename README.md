# Navertia Voice Demo

Demo de un **asistente de voz de reservas en español** para Navertia:

- **`calendar/`**: panel de administración y calendario (PHP 8.4 + MariaDB + Tailwind, estética *liquid glass*
  con modo claro/oscuro), API interna para el bot y la vista **Dial Out** para lanzar llamadas.
- **`bot/`**: bot de voz con **Pipecat 1.12.0** (Twilio / SmallWebRTC → Deepgram → LLM → ElevenLabs) con
  herramientas de reserva, información de tiendas y traspaso real a un comercial.

Consulta [docs/](docs/) (español): [arquitectura](docs/ARQUITECTURA.md), [calendario](docs/CALENDARIO.md),
[bot](docs/BOT.md), [despliegue](docs/DESPLIEGUE.md), [pruebas](docs/PRUEBAS.md),
[escalar con pipecat-flows](docs/ESCALAR-CON-FLOWS.md), [credenciales de desarrollo](docs/CREDENTIALS.md).

## Puesta en marcha (desarrollo)

Requisitos: Docker + Compose, `make`, [`uv`](https://docs.astral.sh/uv/) (para los tests del bot).

```bash
cp .env.example .env        # rellena INTERNAL_API_TOKEN, contraseñas de BD y claves de proveedores
make up                     # construye y levanta php, mariadb, phpmyadmin y bot
make migrate seed           # esquema + datos de demo
```

- Panel: <http://localhost:8090> (usuarios en [docs/CREDENTIALS.md](docs/CREDENTIALS.md), p. ej. `admin@navertia.demo`)
- Bot: <http://localhost:7860/health> · prueba local sin Twilio: <http://localhost:7860/client/>
- phpMyAdmin (solo dev): <http://localhost:8091>

### Twilio en local

```bash
make tunnel     # cloudflared -> actualiza PUBLIC_BASE_URL y recrea el bot
```

Configura el webhook de voz del número en `https://<url-del-túnel>/twilio/voice` y usa **Dial Out** en el panel.

## Comandos

| Comando | |
|---------|--|
| `make up` / `down` / `logs` / `ps` | ciclo de vida del stack de desarrollo |
| `make migrate` / `seed` / `fresh` | base de datos |
| `make css` | compila Tailwind |
| `make test` | PHPUnit + pytest + Playwright |
| `make tunnel` | túnel público al bot |
| `make prod-up` | producción detrás del Traefik del VPS |
| `make prod-seed` | datos mínimos de producción (tiendas, comerciales…; ver docs/DESPLIEGUE.md) |

## Notas

- Las pruebas **no** hacen llamadas reales de Twilio.
- Sin email ni SMS. Sin `pipecat-flows` (recomendado para escalar; ver docs).
- Especificación del alcance inicial: [`openspec/changes/initial-build/`](openspec/changes/initial-build/).
