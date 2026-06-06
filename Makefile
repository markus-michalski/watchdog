# watchdog — lightweight monitoring tool
#
# Usage:
#   make help          — list all targets
#   make stage-update  — rebuild image + restart stage (the normal deploy command)
#   make live-update   — rebuild image + restart live without downtime
#   make local-up      — start local dev containers (docker-compose.yml, with code mount)

SHELL := /bin/bash
DC       := docker compose -f compose.stage.yml
DC_LIVE  := docker compose -f docker-compose.prod.yml
DC_LOCAL := docker compose

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

## -- Stage containers ---------------------------------------------------------

.PHONY: stage-update
stage-update: ## Rebuild image + restart stage (normal deploy: git pull && make stage-update)
	$(DC) build app worker scheduler
	$(DC) up -d --no-deps app worker scheduler
	@sleep 3
	$(DC) exec app php bin/console cache:clear --no-warmup
	$(DC) exec app php bin/console cache:warmup
	$(DC) exec app php bin/console tailwind:build --minify
	$(DC) exec app php bin/console asset-map:compile
	$(DC) exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "Stage updated → http://localhost:8087"

.PHONY: stage-up
stage-up: ## Start stage containers (does NOT rebuild — use stage-update for deploys)
	$(DC) up -d
	$(DC) exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "App     → http://localhost:8087"
	@echo "Mailpit → http://localhost:8128"

.PHONY: stage-down
stage-down: ## Stop stage containers
	$(DC) down

.PHONY: stage-restart
stage-restart: stage-down stage-up ## Restart stage containers

.PHONY: stage-logs
stage-logs: ## Tail all stage container logs
	$(DC) logs -f

.PHONY: stage-app-logs
stage-app-logs: ## Tail stage app container logs only
	$(DC) logs -f app

.PHONY: stage-shell
stage-shell: ## Open shell in stage app container
	$(DC) exec app sh

.PHONY: stage-build
stage-build: ## Rebuild stage image (no cache) — use stage-update for normal deploys
	$(DC) build --no-cache app worker scheduler

## -- Local dev (docker-compose.yml, code mount) --------------------------------

.PHONY: local-up
local-up: ## Start local dev containers (code mount, Mailpit)
	$(DC_LOCAL) up -d
	@echo "App     → http://localhost:8087"
	@echo "Mailpit → http://localhost:8128"

.PHONY: local-down
local-down: ## Stop local dev containers
	$(DC_LOCAL) down

.PHONY: local-shell
local-shell: ## Open shell in local app container
	$(DC_LOCAL) exec app sh

## -- Live containers ----------------------------------------------------------

.PHONY: live-up
live-up: ## Start live containers (does NOT rebuild — use live-update for deploys)
	$(DC_LIVE) up -d
	$(DC_LIVE) exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "Live app → http://127.0.0.1:8086"

.PHONY: live-down
live-down: ## Stop live containers
	$(DC_LIVE) down

.PHONY: live-update
live-update: ## Rebuild + redeploy live without downtime, then migrate
	$(DC_LIVE) build app worker scheduler
	$(DC_LIVE) up -d --no-deps app worker scheduler
	@sleep 3
	$(DC_LIVE) exec app php bin/console cache:clear --no-warmup
	$(DC_LIVE) exec app php bin/console cache:warmup
	$(DC_LIVE) exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "Live updated."

.PHONY: live-build
live-build: ## Rebuild live image (no cache) — use live-update for normal deploys
	$(DC_LIVE) build --no-cache app worker scheduler

.PHONY: live-logs
live-logs: ## Tail live container logs
	$(DC_LIVE) logs -f

.PHONY: live-shell
live-shell: ## Open shell in live app container
	$(DC_LIVE) exec app sh

## -- Symfony ------------------------------------------------------------------

.PHONY: migrate
migrate: ## Run Doctrine migrations (stage)
	$(DC) exec app php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: cc
cc: ## Clear Symfony cache (stage)
	$(DC) exec app php bin/console cache:clear

## -- Code quality -------------------------------------------------------------

.PHONY: lint
lint: ## Run container + twig lint
	$(DC) exec app php bin/console lint:container
	$(DC) exec app php bin/console lint:twig templates/ --env=prod
	$(DC) exec app composer validate --no-check-publish

.PHONY: stan
stan: ## Run PHPStan (level 8)
	$(DC) exec app vendor/bin/phpstan analyse src --level=8

.PHONY: cs
cs: ## Check code style (dry-run)
	$(DC) exec app vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: fix
fix: ## Fix code style
	$(DC) exec app vendor/bin/php-cs-fixer fix

.PHONY: test
test: ## Run PHPUnit
	$(DC) exec app php bin/phpunit

.PHONY: smoke
smoke: ## Run all smoke tests
	$(DC) exec app php bin/console lint:container
	$(DC) exec app php bin/console lint:twig templates/ --env=prod
	$(DC) exec app php bin/console doctrine:schema:validate --skip-sync
	$(DC) exec app composer validate --no-check-publish
