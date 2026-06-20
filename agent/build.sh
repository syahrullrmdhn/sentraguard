#!/usr/bin/env bash
# SentraGuard Agent — cross-compile build script
# Requires Go 1.22+ (installed at /usr/local/go-1.22 on this server)
set -euo pipefail

VERSION="${VERSION:-1.0.0}"
OUT_DIR="build"
LDFLAGS="-s -w -X main.Version=${VERSION}"

export GOROOT="${GOROOT:-/usr/local/go-1.22}"
export PATH="$GOROOT/bin:$PATH"

mkdir -p "$OUT_DIR"

echo "==> go vet (windows target)"
GOOS=windows GOARCH=amd64 go vet ./...

echo "==> Building Windows amd64 (production target)"
GOOS=windows GOARCH=amd64 go build -ldflags "$LDFLAGS" -o "$OUT_DIR/sentraguard-agent.exe" ./cmd/sentraguard-agent

echo "==> Building Linux amd64 (dev)"
GOOS=linux GOARCH=amd64 go build -ldflags "$LDFLAGS" -o "$OUT_DIR/sentraguard-agent" ./cmd/sentraguard-agent

echo "==> Done:"
file "$OUT_DIR"/sentraguard-agent.exe "$OUT_DIR"/sentraguard-agent
ls -la "$OUT_DIR"
