# watchdog

Lightweight website and container monitoring. Plugin-based check system, SQLite, FrankenPHP — no external dependencies.

## Stack

- **Symfony 7.4 LTS** + PHP 8.3
- **FrankenPHP** (Caddy-based, single binary)
- **SQLite** via Doctrine ORM — no database container
- **Symfony Messenger** — async check execution and email alerts
- **Symfony Scheduler** — recurring checks per configured interval
- **Tailwind CSS** via CDN

## Containers

| Container   | Purpose                                      |
|-------------|----------------------------------------------|
| `app`       | FrankenPHP web server (UI + API)             |
| `worker`    | Messenger consumer — runs checks, sends mails|
| `scheduler` | Triggers checks on configured interval       |
| `mailpit`   | Local mail catch-all (stage/dev only)        |

The worker container needs access to `/var/run/docker.sock` for Docker container health checks.

## Compose Files

| File                    | Purpose                              | Ports       |
|-------------------------|--------------------------------------|-------------|
| `compose.stage.yml`     | Stage server — prod build, no mounts | 8087        |
| `docker-compose.prod.yml` | Live server — prod build           | 8086        |
| `docker-compose.yml`    | Local dev — code mount, hot reload   | 8087 + 8128 |

## First-Time Setup

### 1. Clone and configure

```bash
git clone git@github.com:markus-michalski/watchdog.git /opt/watchdog-stage
cd /opt/watchdog-stage
cp .env.example .env.local
```

Edit `.env.local` and fill in:

```
APP_SECRET=<random 32-char string>
APP_ADMIN_USER=admin
APP_ADMIN_PASSWORD_HASH=   # see below
MAILER_DSN=smtp://user:pass@smtp.example.com:587
MAILER_FROM=watchdog@yourdomain.com
DEFAULT_URI=https://stage.watchdog.yourdomain.com
```

### 2. Generate password hash

```bash
# Option A: via Docker (no local PHP needed)
docker run --rm dunglas/frankenphp:1-php8.3-alpine php -r \
  "echo password_hash('yourpassword', PASSWORD_BCRYPT) . PHP_EOL;"

# Option B: if PHP is installed locally
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT) . PHP_EOL;"

# Option C: after first make stage-build
docker exec watchdog-stage-app php bin/console security:hash-password
```

### IMPORTANT — Dollar signs in `.env.local`

Docker Compose interpolates `$` in env file values, even inside quotes.  
Bcrypt hashes contain multiple `$` characters. Every `$` must be doubled to `$$`:

```
# Wrong — Docker Compose mangles the hash
APP_ADMIN_PASSWORD_HASH=$2y$13$abc...

# Correct
APP_ADMIN_PASSWORD_HASH=$$2y$$13$$abc...
```

Same applies to `APP_SECRET` if it contains `$`.

### 3. Build and start

```bash
make stage-build
make stage-up
```

## Make Commands

```
make help          List all targets

# Stage (compose.stage.yml — server)
make stage-up      Start containers + run migrations
make stage-down    Stop containers
make stage-restart Stop then start
make stage-build   Rebuild image without cache
make stage-logs    Tail all container logs
make stage-shell   Shell into app container

# Live (docker-compose.prod.yml)
make live-up       Start containers + run migrations
make live-down     Stop containers
make live-update   Zero-downtime rebuild + redeploy
make live-build    Rebuild image without cache
make live-logs     Tail all container logs
make live-shell    Shell into app container

# Local dev (docker-compose.yml — code mount)
make local-up      Start with live code reload
make local-down    Stop
make local-shell   Shell into app container

# Symfony
make migrate       Run pending migrations
make cc            Clear cache

# Code quality
make lint          Container + Twig lint
make stan          PHPStan level 8
make cs            Code style check (dry-run)
make fix           Code style autofix
make test          PHPUnit
make smoke         Full smoke test suite
```

## Updates (live)

```bash
git pull
make live-update   # builds, redeploys, migrates
```

## Available Checks

### HTTP Reachability (`http`)

Checks if a URL returns an expected HTTP status code.

Default config:
```json
{
  "expected_status_codes": [200, 201, 301, 302],
  "timeout": 10
}
```

Supports BasicAuth: set `basicAuthUser` and `basicAuthPassword` on the site — the check picks it up automatically.

### Docker Container Health (`docker`)

Checks if a named container is running and healthy via Docker socket.

Config:
```json
{
  "container_name": "my-container-name"
}
```

- Container running + healthcheck healthy → OK
- Container running + no healthcheck defined → OK
- Container running + healthcheck starting → Unknown
- Container not running or unhealthy → Fail

The worker container must have `/var/run/docker.sock` mounted (already configured in compose files).

## Adding a New Check Type

Create a class in `src/Check/` implementing `CheckInterface` and tag it:

```php
<?php

use App\Check\CheckInterface;
use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class MyCheck implements CheckInterface
{
    public function getType(): string { return 'my_check'; }
    public function getLabel(): string { return 'My Custom Check'; }
    public function getDefaultConfig(): array { return []; }
    public function run(SiteCheck $check): CheckResult { ... }
}
```

No registration needed — `#[AutoconfigureTag]` + `#[AutowireIterator]` wires it automatically.

## Alert Logic

Alerts are sent via email on status transitions only:

- `ok → fail` → failure notification
- `fail → ok` → recovery notification
- Consecutive failures → no repeated alerts (spam prevention)

Configure alert recipients per site in the UI.

## Mailpit (Stage/Dev)

Emails are caught by Mailpit and not actually delivered. Access the web UI at:

- Stage server: `http://127.0.0.1:8128` (or via SSH tunnel)
- Local dev: `http://localhost:8128`
