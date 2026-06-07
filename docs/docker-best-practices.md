# Docker Best Practices — Symfony + FrankenPHP

Erkenntnisse aus dem watchdog-Projekt (2026-06). Für mm-dev-toolkit / project-types/docker.

---

## Compose-Dateinamen-Konvention

Alle Projekte verwenden einheitlich:

```
compose.yml          # Lokale Entwicklung (code-mount, mailpit, öffentlicher Port)
compose.stage.yml    # Stage (baked code, image: watchdog-stage, nur 127.0.0.1)
compose.live.yml     # Live/Prod (baked code, image: watchdog-live, nur 127.0.0.1)
```

**Niemals:** `docker-compose.yml`, `docker-compose.prod.yml` — alter Naming-Standard.

---

## Multi-Stage Dockerfile: Drei Targets

```dockerfile
FROM dunglas/frankenphp:1-php8.3-alpine AS base
# ... base setup (php extensions, timezone, composer binary)

# Production: no dev-deps, assets compiled, worker mode
FROM base AS prod
ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction
COPY . .
RUN composer dump-autoload --optimize \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile

# Stage: dev-deps kept (profiler), assets compiled in prod mode
FROM base AS stage
ENV APP_ENV=prod
RUN composer install --no-scripts --no-autoloader --no-interaction
COPY . .
RUN composer dump-autoload \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile

# Local dev: code mounted, no asset compilation in image
FROM base AS dev
ENV APP_ENV=dev
RUN composer install --no-scripts --no-autoloader --no-interaction
COPY . .
RUN composer dump-autoload
```

**Warum drei?**
- `prod`: maximale Performance, kein Debug-Overhead
- `stage`: Assets im Image (wie prod), aber dev-deps verfügbar für Debugging
- `dev`: Code per Volume gemountet → Hot-Reload ohne Rebuild

**Kritisch:** `stage` braucht `ENV APP_ENV=prod` — ohne ihn werden Assets im dev-Modus kompiliert, auch wenn die Runtime-Env `prod` sagt.

---

## Shared Image-Tag — kein 3x Build

Wenn `worker` und `scheduler` dasselbe Image wie `app` brauchen:

```yaml
# compose.stage.yml
services:
  app:
    build:
      context: .
      target: stage
    image: watchdog-stage      # build + tag

  worker:
    image: watchdog-stage      # nur tag, kein build
    pull_policy: never         # kein Registry-Pull auf fresh host
    command: php bin/console messenger:consume ...

  scheduler:
    image: watchdog-stage      # nur tag, kein build
    pull_policy: never
    command: php bin/console scheduler:run ...
```

**`pull_policy: never` ist Pflicht** — ohne ihn versucht Compose auf einem frischen Host
das Image aus einer Registry zu holen (und scheitert, weil es nur lokal existiert).

**Makefile-Konsequenz:** Nur einmal bauen:
```makefile
$(DC) build app    # nicht: build app worker scheduler
```

---

## Ephemeral Cache via Anonymous Volume

Symfony-Cache darf nicht vom persistenten Daten-Volume überschrieben werden:

```yaml
volumes:
  - watchdog_stage_data:/app/var      # persistent: DB, logs, share
  - /app/var/cache                    # anonymous: shadowt cache im named volume
```

Der anonymous volume (`/app/var/cache`) shadowt das Unterverzeichnis des named volume.
Ohne ihn würde Docker das leere `var/cache/` aus dem named volume nutzen → App startet
ohne kompilierten Container.

---

## TRUSTED_PROXIES hinter Reverse Proxy

Symfony benötigt diese Konfiguration, wenn ein nginx/Caddy-Proxy vorgelagert ist:

```yaml
# compose.stage.yml / compose.live.yml
environment:
  TRUSTED_PROXIES: "REMOTE_ADDR"
```

`REMOTE_ADDR` vertraut dem direkt verbundenen Client — sicher, wenn der Container
nur über `127.0.0.1` erreichbar ist (Portbindung: `127.0.0.1:8087:80`).

**Nicht:** Caddy-Config für TRUSTED_PROXIES missbrauchen — das ist Symfony-Concern.

---

## Port-Bindung

```yaml
# Lokal (compose.yml): öffentlich für Zugriff vom Host
ports:
  - "8087:80"

# Stage/Live: nur localhost, Reverse Proxy (nginx/Caddy) routet rein
ports:
  - "127.0.0.1:8087:80"
```

---

## Deploy-Workflow (stage-update / live-update)

```makefile
stage-update:
    $(DC) build app                                    # 1. Image bauen (assets bereits drin)
    $(DC) up -d --no-deps app worker scheduler         # 2. Container neu starten
    $(DC) exec app sh -c 'i=0; until php bin/console about > /dev/null 2>&1; do sleep 1; i=$$((i+1)); [ $$i -ge 60 ] && echo "App did not start" && exit 1; done'
    $(DC) exec app php bin/console cache:clear --no-warmup
    $(DC) exec app php bin/console cache:warmup
    $(DC) exec app php bin/console doctrine:migrations:migrate --no-interaction
```

**Kein `sleep N`** — stattdessen warten bis der Container ready ist, mit Timeout:
```bash
i=0; until php bin/console about > /dev/null 2>&1; do
  sleep 1; i=$((i+1)); [ $i -ge 60 ] && echo "App did not start" && exit 1
done
```

**Kein `tailwind:build` / `asset-map:compile` im Deploy** — das gehört ins Dockerfile
(stage/prod-Target), nicht in den Makefile-Deploy-Schritt.

---

## .dockerignore — Wichtige Einträge

```
# Docker files selbst
compose*.yml
compose*.yaml
Dockerfile*

# Symfony-spezifisch (werden im Container neu erzeugt)
vendor/
var/cache/
var/log/
var/tailwind/         # Tailwind-Binary wird beim Image-Build heruntergeladen
public/assets/        # Werden im Image kompiliert

# Lokal, nicht im Image
.env.local
.env.*.local
.git
.idea
.vscode
tests/
```

---

## Häufige Fehler / Anti-Patterns

| Anti-Pattern | Problem | Lösung |
|---|---|---|
| `docker-compose.yml` statt `compose.yml` | Veralteter Standard | Neue Compose-Spec-Namensgebung nutzen |
| 3x `build:` in compose (app + worker + scheduler) | 3x derselbe Build | Shared `image:`-Tag, nur `app` baut |
| `target: dev` für Stage | Dev-Image hat keine kompilierten Assets | Separates `stage`-Target im Dockerfile |
| `stage` ohne `ENV APP_ENV=prod` | Assets im dev-Modus kompiliert | `ENV APP_ENV=prod` ins stage-Target |
| worker/scheduler ohne `pull_policy: never` | Registry-Pull auf frischem Host schlägt fehl | `pull_policy: never` für services ohne `build:` |
| `sleep 3` im Makefile | Fragil unter Last | `until`-Loop mit 60s Timeout |
| `tailwind:build` im Makefile-Deploy | Wiederholte Arbeit, sollte im Dockerfile passieren | Assets ins stage/prod-Target |
| TRUSTED_PROXIES via Caddy-Config | Symfony-Concern in Caddy-Config geleakt | `TRUSTED_PROXIES: "REMOTE_ADDR"` als Env-Var |
| Port ohne `127.0.0.1`-Bind auf Server | Dienst öffentlich exponiert | `127.0.0.1:PORT:80` für stage/live |
