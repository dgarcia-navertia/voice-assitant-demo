# Arquitectura

Demo de un asistente de voz en español que reserva citas en tiendas de **Navertia**. Dos
servicios en un monorepo, con un único `.env` en la raíz.

```
                    +-----------------------------+
 Teléfono <-> Twilio |  wss:// /ws  · webhooks     |
                    +--------------+--------------+
                                   |
                    [Traefik del VPS, solo prod: TLS]
                       |                        |
              /ws, /twilio/*                todo lo demás
                       v                        v
              +----------------+  HTTP+token  +------------------+     +---------+
              | bot (Pipecat)  | -----------> | php (Apache, MVC)| --> | MariaDB |
              | FastAPI :7860  | <----------- | calendar/panel   |     +---------+
              +----------------+  /dial-out   +------------------+
                    |   ^
     Deepgram STT   |   |  ElevenLabs TTS · LLM (Google/Anthropic/OpenRouter/OpenAI)
```

| Servicio | Directorio | Tecnología | Puerto dev |
|----------|-----------|------------|-----------|
| Panel / API interna | `calendar/` | PHP 8.4 MVC propio, Apache, MariaDB, Phinx, Tailwind | 8090 |
| Bot de voz | `bot/` | Pipecat **1.12.0**, FastAPI, uv | 7860 |
| Base de datos | — | MariaDB 11.4 | 3316 |
| phpMyAdmin (solo dev) | — | phpMyAdmin 5.2 | 8091 |

## Flujos

**Llamada saliente (Dial Out).**
1. El usuario escribe el teléfono en `/dial-out` y pulsa *Dial*. El navegador hace `POST /dial-out` **a PHP**
   (con token CSRF).
2. PHP valida E.164 y llama a `POST {BOT_BASE_URL}/dial-out` con `Authorization: Bearer INTERNAL_API_TOKEN`.
   **PHP nunca tiene credenciales de Twilio.**
3. El bot crea la llamada con la API REST de Twilio (TwiML `<Connect><Stream url="wss://…/ws">`) y devuelve el `call_sid`.
4. PHP guarda la fila en `calls` (estado `queued`). Twilio envía *status callbacks* a `POST /twilio/status`
   del bot; el bot valida la firma y los reenvía a PHP (`POST /mcp/calls/status`).
5. La vista consulta `GET /dial-out/status/{sid}` cada 1,5 s y pinta queued → ringing → in-progress → completed.
6. Al terminar, el bot guarda la transcripción (`POST /mcp/transcripts/batch`, `sid` = `CallSid`) y la vista
   muestra el enlace a `/calls/{sid}`.

**Llamada entrante.** Twilio hace `POST /twilio/voice`; el bot responde con TwiML `<Connect><Stream>` y el
mismo pipeline atiende. (Implementado aunque el número no reciba llamadas ahora mismo.)

**Traspaso a comercial (`commercial_handoff`).** El bot registra un *lead* en PHP y actualiza la llamada viva
en Twilio con TwiML `<Say>` + `<Dial>HANDOFF_PHONE_NUMBER</Dial>`. El puente de audio de Pipecat se cierra y la
llamada continúa entre el cliente y el comercial.

## Decisiones clave

- **Sin `pipecat-flows`.** Un solo *system prompt* en español + *function calling*. Es la opción más simple
  para esta demo. Para escalar la conversación, **`pipecat-flows` es el camino recomendado**: ver
  [ESCALAR-CON-FLOWS.md](ESCALAR-CON-FLOWS.md).
- **Copia y poda** del calendario de referencia: el esquema Phinx se copia entero, pero solo se cablean las tablas
  necesarias (tiendas, horarios, comerciales, clientes, citas, usuarios, transcripciones, leads y llamadas).
- **Sin email ni SMS.**
- **Secreto compartido en las dos direcciones** (`INTERNAL_API_TOKEN`): bot → PHP (`/mcp/*`) y PHP → bot (`/dial-out`).
- **Transcripciones idempotentes**: reenviar el lote de una llamada reemplaza, no duplica.
- **Tailwind sin CDN**: CLI *standalone* en Docker (`make css`); en prod se compila en una etapa del `Dockerfile.prod`.

## Esquema de datos

Las migraciones de `calendar/src/db/migrations/` son:

1. `20260916120000_baseline_schema.php`: esquema completo copiado de la referencia (incluye tablas que
   esta demo **no cablea**: `products`, `providers`, `legal_documents`, `notifications`, `appointment_confirmation_sms`,
   `appointment_reschedule_calls`, `commercial_transfer_*`…).
2. `20260929100000_voice_demo_tables.php`: `calls`, `leads`, y ensancha `stores.phone_number` / `stores.type`.

Tablas cableadas: `stores`, `store_schedules`, `users`, `commercials`, `commercial_week_patterns`,
`schedule_overrides`, `blocking_events`, `holidays`, `clients`, `appointments`, `services`, `settings`,
`api_keys`, `transcripts`, `leads`, `calls`.
