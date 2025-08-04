#!/bin/bash
set -e

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN_DIR="$BASE_DIR/bin"

echo "🔧 Creating bin directory if it doesn't exist..."
mkdir -p "$BIN_DIR"

echo "🔨 Building kagome-cli for macOS..."
cd "$BASE_DIR/kagome/cmd/kagome"
go build -o "$BIN_DIR/kagome-cli-mac"

echo "🔨 Building kagome-cli for Linux (64-bit)..."
GOOS=linux GOARCH=amd64 go build -o "$BIN_DIR/kagome-cli-linux"

echo "✅ Binaries built:"
echo " - $BIN_DIR/kagome-cli-mac"
echo " - $BIN_DIR/kagome-cli-linux"
