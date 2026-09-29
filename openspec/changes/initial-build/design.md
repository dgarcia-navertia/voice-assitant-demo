# Design: Navertia Voice Demo

## Arquitectura

```
Telefono <-> Twilio <--wss--> [Caddy] --> bot (FastAPI + Pipecat) --HTTP+token--> php (/mcp/*) --> MariaDB
                                 |                                              ^
                                 +--> php (Apache) <-- navegador (panel)  -------+ POST /dial-out (token)
```

- PHP nunca guarda credenciales de Twilio: la vista Dial Out hace POST al backend PHP y este
  llama a `POST {BOT_BASE_URL}/dial-out` con `INTERNAL_API_TOKEN`.
- El bot recibe los callbacks de estado de Twilio (`/twilio/status`), valida la firma y los
  reenvia a PHP (`POST /mcp/calls/status`). PHP guarda en la tabla `calls`; la UI hace polling
  a `GET /dial-out/status/{call_sid}` (PHP).
- El `sid` de transcripcion es el `CallSid` de Twilio, asi la UI enlaza llamada -> transcripcion.

## Decisiones
1. **Sin flows**: un prompt de sistema + function calling. Escalar con `pipecat-flows` queda
   documentado como camino recomendado (docs/ESCALAR-CON-FLOWS.md).
2. **Copia y poda** del calendario de referencia: se conserva `Router`, `Model`, servicios de
   disponibilidad; se elimina SMS/email/catalogo/reschedule-call.
3. **Migraciones**: se copia el baseline completo y se anade una migracion nueva con la API de
   Phinx (`leads`, `calls`, ajustes de `transcripts`).
4. **Tailwind**: CLI standalone descargada en una etapa Docker (`css` stage); el CSS compilado
   se sirve local, no hay CDN. Variables CSS + tokens Tailwind para la paleta.
5. **Idle**: mecanismo de inactividad de Pipecat 1.12 (ver bot/src/pipeline.py).
6. **Handoff**: `calls(sid).update(twiml=<Response><Say/><Dial>...)` sobre la llamada viva.
7. **Prod**: Caddy unico punto de entrada; dominio unico, rutas del bot proxied; PHP y bot en
   redes internas; BD sin puerto publicado; sin phpMyAdmin.

## Contrato de la API interna (PHP `/mcp/*`)
Ver `specs/calendar/spec.md`.
