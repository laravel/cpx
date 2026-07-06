#!/usr/bin/env bash

set -e

# Run from the repository root so box.json and the source paths resolve.
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

# An optional version bakes an explicit tag; without one the placeholder resolves to "dev".
VERSION="${1:-}"
VERSION_FILE="src/Version.php"
VERSION_BACKUP=""

# The pinned copy is cached under builds/ (gitignored) and only downloaded once.
BOX_VERSION="4.7.0"
BOX_URL="https://github.com/box-project/box/releases/download/${BOX_VERSION}/box.phar"
BOX_PHAR="builds/box-${BOX_VERSION}.phar"

CYAN='\033[0;36m'
GREEN='\033[0;32m'
RESET='\033[0m'

info() {
    echo -e "${CYAN}$1${RESET}"
}

success() {
    echo -e "${GREEN}$1${RESET}"
}

cleanup() {
    # Restore the version placeholder if the build was interrupted after we replaced it.
    if [ -n "$VERSION_BACKUP" ] && [ -f "$VERSION_BACKUP" ]; then
        mv "$VERSION_BACKUP" "$VERSION_FILE"
    fi
}
trap cleanup EXIT

info "Installing dependencies..."
composer install --no-interaction --quiet

if [ ! -f "$BOX_PHAR" ]; then
    info "Downloading Box ${BOX_VERSION}..."
    mkdir -p builds
    curl -sSL -o "${BOX_PHAR}.tmp" "$BOX_URL"
    mv "${BOX_PHAR}.tmp" "$BOX_PHAR"
fi

if [ -n "$VERSION" ]; then
    # Bake the version into the binary, mirroring Laravel Zero's app:build --build-version.
    info "Baking version $VERSION..."
    VERSION_BACKUP="${VERSION_FILE}.bak"
    cp "$VERSION_FILE" "$VERSION_BACKUP"
    sed "s/@git_version@/${VERSION}/" "$VERSION_BACKUP" > "$VERSION_FILE"
fi

info "Building binary..."
php -d phar.readonly=0 "$BOX_PHAR" compile

if [ -n "$VERSION_BACKUP" ]; then
    # Restore the source placeholder now that the version is baked into the phar.
    mv "$VERSION_BACKUP" "$VERSION_FILE"
    VERSION_BACKUP=""
fi

success "Built builds/cpx"
