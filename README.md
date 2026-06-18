# watchdog

Lightweight website and container monitoring. Plugin-based check system, agent-based remote monitoring, SQLite, FrankenPHP — no external dependencies.

![License: PolyForm NC 1.0.0](https://img.shields.io/badge/license-PolyForm%20NC%201.0.0-red.svg?style=for-the-badge)

**Full documentation:** [faq.markus-michalski.net/en/watchdog](https://faq.markus-michalski.net/en/watchdog)

## Stack

- **Symfony 8.1** + PHP 8.4
- **FrankenPHP** (Caddy-based, single binary)
- **SQLite** via Doctrine ORM — no database container
- **Symfony Messenger** — async check execution and email alerts
- **Symfony Scheduler** — recurring checks per configured interval
- **Tailwind CSS** via CDN

## Quick Start

### 1. Clone and configure

```bash
git clone https://github.com/markus-michalski/watchdog.git /opt/watchdog
cd /opt/watchdog
cp .env.example .env.local
```

Edit `.env.local`:

```
APP_SECRET=<random 32-char string>
APP_ADMIN_USER=admin
APP_ADMIN_PASSWORD_HASH=   # see below
MAILER_DSN=smtp://user:pass@smtp.example.com:587
MAILER_FROM=watchdog@yourdomain.com
DEFAULT_URI=https://watchdog.yourdomain.com
```

### 2. Generate password hash

```bash
docker run --rm dunglas/frankenphp:1-php8.4-alpine php -r \
  "echo password_hash('yourpassword', PASSWORD_BCRYPT) . PHP_EOL;"
```

Bcrypt hashes contain `$` — every `$` must be doubled to `$$` in `.env.local`:

```
# Wrong
APP_ADMIN_PASSWORD_HASH=$2y$13$abc...

# Correct
APP_ADMIN_PASSWORD_HASH=$$2y$$13$$abc...
```

### 3. Build and start

```bash
make stage-build
make stage-up
```

### 4. Enable the host agent

In the dashboard, go to **Settings → Agents**, create an agent, copy the token into
`WATCHDOG_AGENT_TOKEN` in `.env.local`, then restart the agent container:

```bash
docker compose -f compose.stage.yml up -d agent
```

## Make Commands

```
make stage-up      Start containers + run migrations
make stage-down    Stop containers
make stage-build   Rebuild image without cache
make stage-logs    Tail all container logs
make live-update   Zero-downtime rebuild + redeploy (live)
make test          PHPUnit
make stan          PHPStan level 8
```

Run `make help` for the full list.

## Documentation

Full documentation — check type reference, agent setup for remote servers, writing
custom checks:

**[faq.markus-michalski.net/en/watchdog](https://faq.markus-michalski.net/en/watchdog)**

## License

[PolyForm Noncommercial License 1.0.0](LICENSE.md) — source-available,
personal and non-commercial use only. Not OSI Open Source.
Commercial use requires explicit permission; contact the maintainer.
