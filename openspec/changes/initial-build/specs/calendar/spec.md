# Calendar Specification (delta: initial-build)

## Requirements

### Requirement: Internal API authentication
Todas las rutas `/mcp/*` MUST exigir `Authorization: Bearer <INTERNAL_API_TOKEN>` o
`X-API-Key`. Sin token valido MUST devolver 401 `{"error":"UNAUTHORIZED"}`.
Los errores MUST ser `{"error": "<mensaje>"}` con 4xx.

### Requirement: Internal API endpoints
- `GET /mcp/stores` -> `{stores:[{id,name,address,type,phone_number,schedule:{mon_to_friday,saturday}|null}]}`
- `GET /mcp/stores/{id}` -> una tienda (mismo shape) o 404.
- `GET /mcp/availability?store_id&date=YYYY-MM-DD[&service_id]` ->
  `{date, slots:[{time:"HH:MM", commercial_id, commercial_name}], commercials:{}}`
- `GET /mcp/clients/by-phone?phone=` -> `{client:{id,client_name,client_phone}}` o 404.
- `POST /mcp/clients` `{client_name, client_phone, client_email?, client_type?}` ->
  201 `{client:{id,client_name,client_phone,client_email,client_type}}`. `client_type` por defecto `particular`.
- `POST /mcp/appointments` `{store_id, client_id, starts_at:"YYYY-MM-DD HH:MM:SS", service_id?, commercial_id?}` ->
  201 cita con detalles; 409 si no hay hueco.
- `POST /mcp/transcripts/batch` `{sid, turns:[{role:"user"|"assistant", transcript_text, turn_index, interrupted?}]}` ->
  201 `{saved:n}`. (`POST /mcp/transcripts` de un turno tambien existe.)
- `POST /mcp/leads` `{call_sid?, name?, phone, store_id?, reason?, source?, transferred?}` -> 201 `{lead:{id,...}}`.
- `POST /mcp/calls/status` `{call_sid, status, to?, from?, direction?, duration?, error?}` -> 200 `{call:{...}}` (upsert).
  `status` in queued|ringing|in-progress|completed|failed|busy|no-answer|canceled.

### Requirement: Dial Out view
`GET /dial-out` (auth) muestra input pill de telefono + selector de prefijo con todos los paises,
banderas y busqueda, por defecto Espana +34. `POST /dial-out` valida E.164, llama al bot
`POST {BOT_BASE_URL}/dial-out` con el token y guarda la fila en `calls`. `GET /dial-out/status/{sid}`
devuelve JSON con el estado; la vista hace polling y enlaza `/calls/{sid}` (transcripcion) al terminar.

### Requirement: Branding and theme
Todo el texto de marca MUST decir Navertia (ningun "Amado Salvador"). Paleta como variables CSS y
tokens Tailwind: primary #075056, accent #DC3225, text/dark #26180F, secondary #6A959A. Estilo liquid
glass con modo claro/oscuro.

### Requirement: No email/SMS
No hay PHPMailer, Mailpit ni SMS.

### Requirement: Seed & auth
Login con roles admin/manager/commercial. Usuarios sembrados documentados en `docs/CREDENTIALS.md`.
