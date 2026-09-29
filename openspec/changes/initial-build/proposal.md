# Proposal: Construccion inicial de Navertia Voice Demo

## Intencion

Demo de un asistente de voz en espanol que reserva citas en tiendas de Navertia, con un
panel de administracion (calendario) y un bot de voz. Es una base limpia derivada de dos
proyectos de referencia (`amadosalvador-calendar` y `webrtc`), rebrandeada a Navertia y
sin `pipecat-flows`.

## Alcance

### Incluido
- **calendar/** (PHP): login con roles, tiendas y horarios, comerciales, clientes, citas,
  disponibilidad, transcripciones y leads, API interna con token para el bot, vista
  **Dial Out** (llamada saliente + estado en vivo + enlace a transcripcion), estetica
  "liquid glass" con modo claro/oscuro, Tailwind compilado (sin CDN).
- **bot/** (Pipecat 1.12.0): pipeline nuevo Twilio/SmallWebRTC -> Deepgram -> LLM -> ElevenLabs,
  proveedores conmutables por env, un unico prompt en espanol, tools `book_appointment`
  (+ ayudantes), `get_company_info`, `commercial_handoff` (transferencia real por TwiML
  `<Dial>`), manejo de inactividad, servidor FastAPI (`/ws`, webhook entrante, `/dial-out`,
  callback de estado, `/webrtc`, `/health`), transcripciones al API de PHP.
- Despliegue: `docker-compose.prod.yml` con builds multi-stage, usuarios no root,
  healthchecks, restart y Caddy con TLS automatico.
- Documentacion en `docs/` y `CLAUDE.md` nuevos.

### Excluido
- Email (PHPMailer/Mailpit) y SMS.
- Catalogo/productos/proveedores/legal: el esquema se copia entero pero no se cablean.
- `pipecat-flows`: se documenta como la via recomendada para escalar la conversacion.
- Llamadas reales de Twilio en tests.

## Riesgos
- APIs de Pipecat 1.12.0 distintas de 1.11: se verifica cada import contra el indice.
- `stores.phone_number` del esquema base es `VARCHAR(9)` (formato espanol).
- Transferencia real solo probable con numero Twilio y tunel publico (no se prueba aqui).
