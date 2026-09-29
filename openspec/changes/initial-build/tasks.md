# Tasks: initial-build

## 1. Fundacion
- [x] git init, .gitignore, .env.example, .env local, openspec
## 2. Calendar
- [x] Copiar y podar app/config/migraciones de la referencia; rebrand a Navertia
- [x] Migracion nueva: leads, calls
- [x] Seeders (usuarios, tiendas, horarios, comerciales, clientes, citas)
- [x] API interna /mcp/* con token
- [x] Tailwind + tema liquid glass + logo local
- [x] Vistas: login, dashboard, citas, tiendas, comerciales, clientes, usuarios, leads, llamadas, Dial Out
- [x] Tests PHPUnit + Playwright e2e
## 3. Bot
- [x] Config/providers, pipeline, tools, prompts, clients, server, tests
## 4. Infra y docs
- [x] docker-compose dev/prod, Dockerfiles, Caddy, Makefile
- [x] CLAUDE.md, README.md, docs/
## 5. Verificacion
- [x] up, migrate, seed, login, tests, /health

## Notas de verificacion
- `make up migrate seed` desde cero; PHPUnit 90 tests OK; pytest 42 OK; Playwright 40 OK; /health del bot OK.
- Prod probado en local (APP_DOMAIN=localhost): php, bot, db, caddy healthy; wss:// via Caddy con handshake OK.
- No verificado: llamada real de Twilio (prohibido), LLM/STT/TTS reales, TLS con Let's Encrypt real.
