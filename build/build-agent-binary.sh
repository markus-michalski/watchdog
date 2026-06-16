#!/usr/bin/env bash
# Build a self-contained watchdog agent binary using FrankenPHP embed.
#
# Prerequisites:
#   - Docker
#   - Go 1.22+ (only needed for manual build, not Docker-based build)
#
# The resulting binary includes PHP 8.4 and the watchdog agent command.
# It can be deployed to any Linux x86_64 server without additional dependencies.
#
# Usage:
#   chmod +x build/build-agent-binary.sh
#   ./build/build-agent-binary.sh [output-name]

set -euo pipefail

OUTPUT="${1:-watchdog-agent}"
DIST_DIR="$(dirname "$0")/../dist"
APP_DIR="$(dirname "$0")/.."

echo "==> Building watchdog agent binary: ${OUTPUT}"
echo "    Output: ${DIST_DIR}/${OUTPUT}"

mkdir -p "${DIST_DIR}"

# Step 1: Build the Symfony app for production inside a temp container
echo ""
echo "==> Step 1: Preparing production Symfony app..."

docker run --rm \
  -v "${APP_DIR}:/app" \
  -w /app \
  dunglas/frankenphp:1-php8.4-alpine \
  sh -c '
    composer install --no-dev --optimize-autoloader --no-interaction
    APP_ENV=prod php bin/console cache:warmup
  '

# Step 2: Build FrankenPHP static binary with embedded PHP app
# The FrankenPHP "embed" feature packages the PHP app into the binary.
# See: https://frankenphp.dev/docs/embed/
echo ""
echo "==> Step 2: Building static binary (this may take a while)..."

docker run --rm \
  -v "${APP_DIR}:/go/src/app" \
  -w /go/src/app \
  -e CGO_ENABLED=1 \
  -e GOFLAGS="-tags=embed" \
  --platform linux/amd64 \
  dunglas/frankenphp:latest-builder \
  sh -c '
    apk add --no-cache upx
    go build \
      -ldflags="-s -w -X main.defaultConfigPath=/app/frankenphp.yaml" \
      -tags=embed \
      -o /go/src/app/dist/'"${OUTPUT}"' \
      github.com/dunglas/frankenphp/cmd/frankenphp
    upx --best /go/src/app/dist/'"${OUTPUT}"' || true
  '

echo ""
echo "==> Done! Binary: ${DIST_DIR}/${OUTPUT}"
echo ""
echo "Usage:"
echo "  WATCHDOG_DASHBOARD_URL=https://watchdog.example.com \\"
echo "  WATCHDOG_AGENT_TOKEN=your-token \\"
echo "  ./${OUTPUT} php bin/console watchdog:agent:run"
