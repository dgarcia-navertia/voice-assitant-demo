# Bot Specification (delta: initial-build)

### Requirement: Pipeline
Transport (Twilio WebSocket / SmallWebRTC) -> Deepgram STT -> context aggregator -> LLM -> ElevenLabs TTS.
pipecat-ai MUST estar fijado a 1.12.0. NO se usa pipecat-flows.

### Requirement: Providers via env
`LLM_PROVIDER` in google|anthropic|openrouter|openai; defaults `gemini-3.5-flash-lite`, `nova-3-general` (es),
`eleven_flash_v2_5`, voz `dNjJKg63Fr5AXwIdkATa`. Todos los ajustes en `.env.example`.

### Requirement: Tools
`book_appointment`, `check_availability`, `find_or_create_client`, `get_company_info`, `commercial_handoff`.
Handoff MUST hacer una transferencia real (TwiML `<Dial>` sobre la llamada viva a `HANDOFF_PHONE_NUMBER`)
y registrar un lead en PHP.

### Requirement: Greeting & idle
El bot habla primero: "Soy el asistente virtual de Navertia. ¿En qué te puedo ayudar?".
Tras 20 s de silencio del usuario pregunta "¿Sigues ahí?"; maximo 2 reintentos; despues se despide y cuelga.

### Requirement: Server
`/ws`, `POST /twilio/voice` (entrante), `POST /dial-out` (token), `POST /twilio/status`, `/webrtc`, `/health`.

### Requirement: Transcripts
Al terminar la llamada se guardan las transcripciones en PHP con `sid = CallSid`.
