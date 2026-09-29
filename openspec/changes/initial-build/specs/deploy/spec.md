# Deploy Specification (delta: initial-build)

### Requirement: Production compose
`docker-compose.prod.yml`: builds multi-stage, usuarios no root, healthchecks, `restart: unless-stopped`,
Caddy con TLS automatico sirviendo PHP y haciendo proxy de `wss://` al bot, sin phpMyAdmin, BD sin puerto publicado.

### Requirement: Tunnel
`make tunnel` levanta cloudflared (o ngrok) hacia el bot para pruebas locales con Twilio.
