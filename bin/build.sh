#!/bin/bash
#
# Build the kagome CLI binary for every OS/arch the plugin ships, and drop the
# results into the plugin's bundled resources directory.
#
# The upstream kagome source is NOT vendored in this repo; we build it straight
# from the Go module. kagome v2 is pure Go, so cross-compilation just needs
# GOOS/GOARCH (no cgo, no C toolchain).
#
# Usage:
#   bin/build.sh                 # build all targets at KAGOME_VERSION (default: latest)
#   KAGOME_VERSION=v2.x.y bin/build.sh
#
set -euo pipefail

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$BASE_DIR/resources/kagome"

KAGOME_PKG="github.com/ikawaha/kagome/v2"
KAGOME_VERSION="${KAGOME_VERSION:-latest}"

# OS/ARCH pairs -> output filename suffix (matches Analyzer::detectBinaryPath()).
TARGETS=(
	"linux amd64"
	"linux arm64"
	"darwin arm64"
)

echo "📦 Output dir: $OUT_DIR"
mkdir -p "$OUT_DIR"

echo "🌐 Resolving $KAGOME_PKG@$KAGOME_VERSION ..."
TMP_MOD="$(mktemp -d)"
trap 'rm -rf "$TMP_MOD"' EXIT
(
	cd "$TMP_MOD"
	go mod init kagome-build >/dev/null 2>&1
	go get "${KAGOME_PKG}@${KAGOME_VERSION}"
)

for target in "${TARGETS[@]}"; do
	read -r goos goarch <<<"$target"
	out="$OUT_DIR/kagome-${goos}-${goarch}"
	echo "🔨 Building $goos/$goarch -> $out"
	(
		cd "$TMP_MOD"
		GOOS="$goos" GOARCH="$goarch" CGO_ENABLED=0 \
			go build -trimpath -ldflags "-s -w" -o "$out" "$KAGOME_PKG"
	)
	chmod 0755 "$out"
done

echo "✅ Built binaries:"
for target in "${TARGETS[@]}"; do
	read -r goos goarch <<<"$target"
	f="$OUT_DIR/kagome-${goos}-${goarch}"
	printf ' - %s (%s)\n' "$f" "$(file -b "$f" 2>/dev/null | cut -d, -f1-2)"
done
