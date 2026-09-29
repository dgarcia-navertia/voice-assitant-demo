# Caddy sin root: el binario recibe cap_net_bind_service para abrir 80/443.
FROM caddy:2-alpine
RUN apk add --no-cache libcap \
    && setcap cap_net_bind_service=+ep /usr/bin/caddy \
    && addgroup -S -g 10002 caddy \
    && adduser -S -u 10002 -G caddy -h /data caddy \
    && mkdir -p /data /config \
    && chown -R caddy:caddy /data /config
ENV XDG_DATA_HOME=/data XDG_CONFIG_HOME=/config
USER caddy
