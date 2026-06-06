# Git Context

## Branch
main (on par with origin/main)

## Modified Files
- `.env` — 2 insertions, 1 deletion
- `.env.example` — 7 insertions, 4 deletions

## git diff --stat
 .env         | 2 +-
 .env.example | 9 ++++++---
 2 files changed, 7 insertions(+), 4 deletions(-)

## git diff
```diff
diff --git a/.env b/.env
--- a/.env
+++ b/.env
@@ -2,10 +2,10 @@
 APP_ENV=dev
 APP_DEBUG=0
 APP_SECRET=change-me
 APP_SHARE_DIR=var/share
-DEFAULT_URI=http://localhost:8086
 DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
 MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
 MAILER_DSN=smtp://mailpit:1025
 MAILER_FROM=watchdog@example.com
 APP_ADMIN_USER=admin
 APP_ADMIN_PASSWORD_HASH=not-set
+DEFAULT_URI=http://localhost:8087

diff --git a/.env.example b/.env.example
--- a/.env.example
+++ b/.env.example
@@ -11,15 +11,18 @@
 # Messenger: uses Doctrine transport (SQLite), no extra queue server needed
 MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

-# Used for generating URLs in CLI commands (e.g. emails)
-DEFAULT_URI=http://localhost:8086
-
 ###> watchdog/auth ###
 APP_ADMIN_USER=admin
 # Generate hash with: php bin/console security:hash-password
 APP_ADMIN_PASSWORD_HASH=
 ###< watchdog/auth ###

+###> watchdog/url ###
+# Base URL used in alert emails for links back to the app.
+# Set to your actual domain in .env.local on the server.
+DEFAULT_URI=http://localhost:8087
+###< watchdog/url ###
+
 ###> symfony/mailer ###
 # Local dev (Mailpit via docker-compose): smtp://mailpit:1025
 # Production: smtp://user:pass@smtp.example.com:587
```

## Recent Commits
77d3d2f fix: use local port 8128 for Mailpit (8025 occupied by mm-kreativ)
36de027 feat: initial watchdog monitoring tool scaffold
2db9212 Add initial set of files
