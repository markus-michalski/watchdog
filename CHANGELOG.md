# Changelog

All notable changes to Watchdog will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Nothing yet

### Changed
- Nothing yet

### Deprecated
- Nothing yet

### Removed
- Nothing yet

### Fixed
- Nothing yet

### Security
- Nothing yet

## [1.0.1] - 2026-06-19

### Changed
- delegate UI labels to CheckRegistry via Twig filter (#86)
- limit agent image build on PRs to Dockerfile changes only
- pin clone to latest tag and add update instructions

### Fixed
- replace missed check.label with check_label filter
- show effective warn threshold in load average OK message (#85)
- refresh full check definition on run-now, not just config (#84)

## [1.0.0] - 2026-06-18

### Added
- Initial release of Watchdog — self-hosted uptime and infrastructure monitoring
- **Dashboard** with real-time check status overview, color-coded badges, and client-side filtering by status and check type
- **10+ check types**: HTTP, DNS, SSL Certificate, TCP Port, Disk Space, Docker container health, DockerExec, Log File pattern matching, File Age, Process liveness, Redis, HTTP Content
- **Agent system**: lightweight agent Docker image for remote server checks — config pull API, results push API, agent management UI, on-demand run-now endpoint
- **Scheduler**: interval-based and daily time-anchored check execution, dispatches dynamically without restart
- **Alert emails**: per-check failure and recovery notifications with SMTP support
- **Client / contact book**: group checks by client, manage contacts per client, assign checks to clients
- **Check history**: per-check result history with configurable retention and duration display
- **Schema-driven config forms**: no raw JSON — type-specific fields rendered dynamically
- **Multi-environment Docker setup**: local dev, stage, and live compose targets with FrankenPHP Worker Mode
- **BDFL governance**: PolyForm NC license, CLA, branch protection, community files

### Security
- Timing-safe agent token verification via `hash_equals()` (#66)
- API rate limiting: sliding window, 60 req/min per IP with filesystem-backed cache pool (#67)
- HTTP security headers: CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy (#68)
- Security warning for database DSN credentials in check configuration UI (#69)
- HTTPS enforcement documentation for all deployment environment files (#70, #71)
- ReDoS protection for log file regex patterns via `pcre.backtrack_limit` + non-empty test input (#72)
- SSRF prevention in DNS resolver: rejects hostnames, private/reserved IP ranges (RFC1918, loopback, link-local) (#74)
- Agent-submitted timestamp plausibility window (-5 min / +1 min) to prevent backdated results (#76)
- Replaced weak `APP_SECRET` placeholder with generation instructions (#77)
- Security warnings in `docker-compose.agent.yml` for `pid: host` and full filesystem mount (#78)

[Unreleased]: https://github.com/markus-michalski/watchdog/compare/v1.0.1...HEAD
[1.0.0]: https://github.com/markus-michalski/watchdog/releases/tag/v1.0.0
[1.0.1]: https://github.com/markus-michalski/watchdog/releases/tag/v1.0.1
