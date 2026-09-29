# CLAUDE.md

Demo de un **asistente de voz de reservas en español** para Navertia. Monorepo con dos servicios y un único
`.env` en la raíz. Docs en español, código e identificadores en inglés.

## Estructura

```
calendar/   PHP 8.4 MVC propio (sin framework) + Apache + MariaDB + Phinx + Tailwind
  src/{app,config,public,db/{migrations,seeds}}   tests/ (Playwright)   src/tests/Unit (PHPUnit)
bot/        Pipecat 1.12.0 + FastAPI + uv     src/{server,pipeline.py,prompts,tools,clients,config}  tests/
caddy/      Caddy (solo prod)        scripts/tunnel.sh      docs/      openspec/
```

Documentación: `docs/ARQUITECTURA.md`, `CALENDARIO.md`, `BOT.md`, `DESPLIEGUE.md`, `PRUEBAS.md`,
`ESCALAR-CON-FLOWS.md`, `CREDENTIALS.md` (solo dev). Especificaciones: `openspec/changes/initial-build/`.

## Comandos

```bash
make up                # composer + tailwind + docker compose up (dev)
make migrate seed      # Phinx
make test              # test-php + test-bot + test-e2e
make logs S=bot        # logs
make css               # recompila Tailwind tras tocar vistas/CSS
make tunnel            # túnel HTTPS (cloudflared) al bot para Twilio en local
make prod-up           # producción (Caddy + TLS), ver docs/DESPLIEGUE.md
```

## Reglas del proyecto

- **pipecat-ai está fijado a 1.12.0.** Verifica cada import contra esa versión (Pipecat Context Hub MCP:
  `check_deprecation`, `search_api`; Context7). No copies código de versiones anteriores sin comprobarlo.
  Usa `uv` (`uv sync`, `uv run pytest`).
- **No se usa `pipecat-flows`.** Un solo prompt + function calling. Si la conversación crece, `pipecat-flows` es
  la vía recomendada (`docs/ESCALAR-CON-FLOWS.md`); no lo añadas sin decidirlo.
- **PHP nunca tiene credenciales de Twilio.** Dial Out: navegador -> PHP -> `POST bot/dial-out` (Bearer
  `INTERNAL_API_TOKEN`) -> Twilio. El bot reenvía los estados a `POST /mcp/calls/status`.
- **Nunca hagas llamadas reales de Twilio** (cuestan dinero) en pruebas o verificación. El `.env` local puede contener
  credenciales reales: no llames a `/dial-out` del bot real; los tests usan `TwilioGateway` simulado y `mock-bot.js`.
- **Sin email ni SMS.** El esquema Phinx se copió entero, pero solo se cablean: tiendas, horarios, comerciales,
  clientes, citas, usuarios, transcripciones, leads y llamadas. Catálogo/productos/legal etc. quedan sin cablear.
- **Secretos**: solo en `.env` (gitignored). `.env.example` lleva todos los ajustes sin valores reales. La contraseña
  de los usuarios sembrados es de desarrollo (`docs/CREDENTIALS.md`).
- **API interna** `/mcp/*`: contrato en `openspec/.../specs/calendar/spec.md`; si lo cambias, actualiza también
  `bot/src/clients/php_api.py` y los tests de ambos lados. El `sid` de transcripción es el `CallSid` de Twilio.
- **Tema**: la paleta (#075056, #DC3225, #26180F, #6A959A) vive en variables CSS (`calendar/assets/app.css`) y tokens
  Tailwind (`tailwind.config.js`). Marca: siempre "Navertia". El CSS compilado no se versiona: `make css`.
- **Migraciones**: nunca edites el baseline; añade una migración nueva con la API de Phinx (mismas colaciones que
  las tablas existentes, ver `voice_demo_tables`).
- **Commits**: unidades lógicas; nunca `git push` sin petición explícita.

## Convenciones de código

- PHP: PSR-4 `App\` -> `calendar/src/app/`; controladores finos, lógica en `Services/`, SQL en `Models/`
  (PDO preparado). Vistas PHP con `htmlspecialchars`. Rutas en `config/routes.php` (gana la primera que casa).
- Python: `bot/src` con `from __future__ import annotations`, `loguru`, `httpx` asíncrono; los proveedores se
  construyen en `config/providers.py` y los handlers de herramientas en `tools/handlers.py` devuelven
  `{"ok": bool, ...}` (nunca lanzan al LLM).
