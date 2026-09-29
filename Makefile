.DEFAULT_GOAL := help
.PHONY: help env up down restart build ps logs shell-php composer-install css migrate seed fresh \
        test test-php test-e2e test-bot tunnel prod-build prod-up prod-down prod-logs prod-ps prod-migrate prod-admin admin

DC      = docker compose
DC_PROD = docker compose -f docker-compose.prod.yml --env-file .env
UID_GID = $(shell id -u):$(shell id -g)

help: ## Lista de comandos
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

env: ## Crea .env desde .env.example si no existe
	@test -f .env || { cp .env.example .env && echo ".env creado: rellena los secretos"; }

# --- Desarrollo ------------------------------------------------------------------
composer-install: ## Instala dependencias PHP (vendor/) como tu usuario
	$(DC) --profile tools run --rm -u $(UID_GID) composer install --no-interaction --prefer-dist

css: ## Compila Tailwind -> calendar/src/public/static/app.css (sin CDN)
	$(DC) --profile tools build tailwind
	$(DC) --profile tools run --rm -u $(UID_GID) tailwind

up: env composer-install css ## Levanta php, mariadb, phpmyadmin y bot (dev)
	$(DC) up -d --build --wait
	@echo "PHP: http://localhost:$$(grep -E '^PHP_PORT=' .env | cut -d= -f2)  | Bot: http://localhost:$$(grep -E '^BOT_PORT=' .env | cut -d= -f2)/health"
	@echo "Siguiente paso la primera vez: make migrate seed"

down: ## Para el stack de desarrollo
	$(DC) down

restart: ## Reinicia bot y php
	$(DC) restart php bot

build: ## Reconstruye imagenes
	$(DC) build

ps: ## Estado de los contenedores
	$(DC) ps

logs: ## Logs en vivo (make logs S=bot para un servicio)
	$(DC) logs -f --tail=100 $(S)

shell-php: ## Shell en el contenedor php
	$(DC) exec php bash

migrate: ## Aplica migraciones de Phinx
	$(DC) exec -T php vendor/bin/phinx migrate

seed: ## Siembra datos de demo (idempotente)
	$(DC) exec -T php vendor/bin/phinx seed:run

fresh: ## Borra la base y la recrea (migrate + seed)
	$(DC) exec -T db sh -c 'mariadb -uroot -p"$$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS \`$$MARIADB_DATABASE\`; CREATE DATABASE \`$$MARIADB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$$MARIADB_DATABASE\`.* TO \"$$MARIADB_USER\"@\"%\";"'
	$(MAKE) migrate seed

admin: ## Crea/actualiza un admin en el stack de DESARROLLO (interactivo)
	./scripts/create-admin.sh dev

# --- Tests -------------------------------------------------------------------------
test: test-php test-bot test-e2e ## Toda la bateria (php + bot + e2e)

test-php: ## PHPUnit (dentro del contenedor php)
	$(DC) exec -T php vendor/bin/phpunit --configuration phpunit.xml

test-bot: ## pytest del bot (uv)
	cd bot && uv run pytest -q

test-e2e: ## Playwright e2e en Docker (necesita el stack, migraciones y seeds)
	mkdir -p calendar/tests/results
	$(DC) --profile test run --rm e2e sh -c "npm install --no-audit --no-fund --silent && npx playwright test"

# --- Twilio en local ----------------------------------------------------------------
tunnel: ## Tunel HTTPS publico al bot (cloudflared; TUNNEL=ngrok para ngrok)
	./scripts/tunnel.sh

# --- Produccion (Caddy + TLS) ----------------------------------------------------------
prod-build: ## Construye las imagenes de produccion
	$(DC_PROD) build

prod-up: ## Despliega produccion (migra y arranca)
	$(DC_PROD) up -d --build --wait

prod-down: ## Para produccion
	$(DC_PROD) down

prod-logs: ## Logs de produccion (S=servicio)
	$(DC_PROD) logs -f --tail=100 $(S)

prod-ps: ## Estado de produccion
	$(DC_PROD) ps

prod-migrate: ## Relanza las migraciones en produccion
	$(DC_PROD) run --rm migrate

prod-admin: ## Crea/actualiza el primer admin en PRODUCCION (interactivo, idempotente)
	./scripts/create-admin.sh prod
