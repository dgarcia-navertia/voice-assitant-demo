# Bot de voz (`bot/`)

Pipecat **1.12.0** (fijado en `bot/pyproject.toml` y `uv.lock`), FastAPI, `uv`. Sin `pipecat-flows`.

## Pipeline (`bot/src/pipeline.py`)

```
Transporte (Twilio WebSocket | SmallWebRTC)
  -> Deepgram STT (nova-3-general, es)
  -> agregador de usuario (VAD Silero + inactividad)
  -> LLM (Google | Anthropic | OpenRouter | OpenAI)
  -> ElevenLabs TTS (eleven_flash_v2_5)
  -> transporte
  -> agregador de asistente
```

APIs de Pipecat 1.12 usadas (verificadas contra la versión instalada; no se copió código 1.11):

- `pipecat.pipeline.worker.PipelineWorker` + `PipelineParams` y `pipecat.workers.runner.WorkerRunner`
  (`PipelineTask`/`PipelineRunner` están deprecados).
- `LLMContext(messages, tools=ToolsSchema)` y `LLMContextAggregatorPair(context, user_params=LLMUserAggregatorParams(...))`.
- Herramientas: `FunctionSchema` + `ToolsSchema`, `llm.register_function(nombre, handler)`, `FunctionCallParams.result_callback`.
- Servicios con `Servicio.Settings(...)`: `DeepgramSTTService`, `ElevenLabsTTSService`, `GoogleLLMService`,
  `AnthropicLLMService`, `OpenRouterLLMService`, `OpenAILLMService`.
- Twilio: `parse_telephony_websocket`, `TwilioFrameSerializer` (con `auto_hang_up=False`, ver más abajo),
  `FastAPIWebsocketTransport`. Audio a 8 kHz.
- WebRTC local: `SmallWebRTCRequestHandler`, `SmallWebRTCTransport` y la UI `pipecat_ai_prebuilt`.

## Proveedores por variable de entorno

| Variable | Valores / por defecto |
|----------|-----------------------|
| `LLM_PROVIDER` | `google` (defecto) · `anthropic` · `openrouter` · `openai` |
| `LLM_MODEL_ID` | `gemini-3.5-flash-lite` (defecto; el modelo existe, no lo "corrijas") |
| `LLM_TEMPERATURE` | `0.3` |
| `GOOGLE_API_KEY` / `ANTHROPIC_API_KEY` / `OPENROUTER_API_KEY` / `OPENAI_API_KEY` | clave del proveedor elegido |
| `STT_MODEL_ID`, `STT_LANGUAGE` | `nova-3-general`, `es` (`DEEPGRAM_API_KEY`) |
| `TTS_MODEL_ID`, `TTS_VOICE_ID`, `TTS_LANGUAGE` | `eleven_flash_v2_5`, `dNjJKg63Fr5AXwIdkATa`, `es` (`ELEVEN_LABS_API_KEY`) |
| `GREETING` | "Soy el asistente virtual de Navertia. ¿En qué te puedo ayudar?" |
| `IDLE_TIMEOUT_SECS`, `IDLE_MAX_RETRIES` | `20`, `2` |
| `BOT_TIMEZONE` | `Europe/Madrid` (fecha/hora que ve el LLM) |
| `ENABLE_WEBRTC` | `true` en dev; `false` en prod |

Para cambiar de proveedor basta editar `.env` y `docker compose up -d bot` (ver `bot/src/config/providers.py`).
La configuración tolera claves vacías: los tests y `/health` funcionan sin ellas.

## Prompt y herramientas

Un único *system prompt* en español (`bot/src/prompts/system.py`), centrado en reservar citas. Se le inyectan la
fecha y hora actuales (Madrid) y, en llamadas **salientes**, el teléfono de la persona (no se le pide). El bot
**habla primero** con el saludo de `GREETING`.

| Herramienta | Qué hace |
|-------------|----------|
| `get_company_info()` | Tiendas, direcciones y horarios en vivo (`GET /mcp/stores`). |
| `check_availability(store_id, date)` | Huecos libres (`GET /mcp/availability`), sin duplicados ni horas pasadas. |
| `book_appointment(client_name, store_id, date, time, client_email?, phone?)` | Valida datos, busca al cliente por teléfono, lo crea si es nuevo y crea la cita (`409` -> `slot_taken`). El correo es opcional. |
| `commercial_handoff(reason, caller_name?, store_id?, mode warm/cold)` | Registra el lead en PHP **y** transfiere la llamada real por Twilio (ver abajo). |
| `end_call()` | Cuando la persona se despide o dice que no necesita nada más: dice una despedida fija y cuelga (ver abajo). |

Los errores se devuelven al LLM como `{"ok": false, "message": "..."}`, nunca como excepciones.

### Transferencia real (`commercial_handoff`)

Guarda previa: si otra herramienta ha fallado (`ok: false`) **en el mismo turno** del usuario, el traspaso se
rechaza y se pide al LLM que explique el problema y **pregunte** si quiere que le pasen; con el "sí" (turno nuevo)
se transfiere. Evita que el modelo transfiera sin preguntar ante un error (p. ej. sin tiendas o con la API caída).
El turno se cuenta en `tools/__init__.py:_dispatch` a partir de los mensajes `user` del contexto. Si la persona pide
hablar con alguien, sin fallo previo, se transfiere directamente.

0. El número se pide a PHP **en cada traspaso** (`GET /mcp/settings/handoff`, caché de 45 s, así una edición del
   admin se aplica en menos de un minuto). Si la API falla o devuelve vacío, se usa `HANDOFF_PHONE_NUMBER` del entorno y
   se registra un aviso en el log.
1. Se crea el *lead* (`POST /mcp/leads`) **primero**, para no perderlo si la transferencia falla.
2. Se actualiza la llamada viva con TwiML (`calls(sid).update(twiml=…)`): un `<Say language="es-ES">` opcional (modo
   *warm*), `<Dial timeout="30" callerId="TWILIO_PHONE_NUMBER">HANDOFF_PHONE_NUMBER</Dial>` y un `<Say>` de reserva si
   nadie contesta. Solo funciona si el `call_sid` empieza por `CA` (no en WebRTC) y hay algún número (PHP o `HANDOFF_PHONE_NUMBER`).
3. `auto_hang_up` del serializador está **desactivado a propósito**: si no, colgaría también la llamada recién
   transferida. El bot cuelga por REST al terminar salvo que la llamada se haya transferido.

### Fin de la llamada (`end_call`)

El LLM llama a `end_call` cuando la persona dice claramente que ha terminado ("no, nada más", "adiós"); nunca tras un
"sí". `_dispatch` devuelve el resultado **sin** un nuevo turno del LLM (`FunctionCallResultProperties(run_llm=False)`)
y encola `TTSSpeakFrame(FAREWELL)` + `EndFrame` (lo mismo que la inactividad): la despedida se oye entera, el pipeline
se cierra y `_finish_call` guarda la transcripción y cuelga por REST. Tras una transferencia no hace nada.

### Inactividad

`LLMUserAggregatorParams(user_idle_timeout=IDLE_TIMEOUT_SECS)` dispara el evento `on_user_turn_idle` tras 20 s de
silencio del usuario (el temporizador se suprime durante llamadas a herramientas y turnos activos). `IdleController`
(`bot/src/idle.py`) cuenta los intentos: "¿Sigues ahí?", "¿Sigues ahí? No te oigo.", y tras el máximo de reintentos
(2) se despide con cortesía y cuelga. El contador vuelve a 0 cuando el usuario habla.

## Servidor FastAPI (`bot/src/server/`, puerto 7860)

| Ruta | Descripción |
|------|-------------|
| `GET /health` | Sondeo de salud. |
| `WS /ws` | Media Stream de Twilio. `sid` de transcripción = `CallSid`. |
| `POST /twilio/voice` | Webhook de llamada **entrante**: responde TwiML `<Connect><Stream url="wss://PUBLIC_BASE_URL/ws">`. Valida `X-Twilio-Signature`. |
| `POST /dial-out` | `Authorization: Bearer INTERNAL_API_TOKEN`, cuerpo `{"to": "+34…"}` (E.164). Crea la llamada por REST de Twilio con `statusCallback`. Devuelve `{call_sid, status:"queued", to}`. 401/422/502/503. **No se publica en Traefik**: solo PHP lo llama por la red interna. |
| `POST /twilio/status` | Callback de estado de Twilio (firma validada). Traduce (`initiated`→`queued`…) y reenvía a PHP `POST /mcp/calls/status`; si PHP no responde, devuelve 200 con `relayed:false`. |
| `POST/PATCH /api/offer`, `/client/`, `/webrtc` | Solo con `ENABLE_WEBRTC=true`: prueba local en navegador con SmallWebRTC (sin Twilio). |

Toda la interacción con Twilio pasa por `TwilioGateway` (`bot/src/clients/twilio_client.py`), inyectable y
simulado en los tests.

## Probar en local

```bash
make up && make migrate seed
# Sin Twilio: abre http://localhost:7860/client/ (SmallWebRTC) y habla con el asistente.
# Con Twilio: make tunnel  (ver DESPLIEGUE.md), configura el webhook y usa Dial Out.
```

Tests: `make test-bot` (pytest, 49 casos con proveedores y Twilio simulados).
