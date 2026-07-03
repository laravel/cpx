#!/usr/bin/env bash

set -e

# Run from the repository root so box.json and the source paths resolve.
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

# An optional version bakes an explicit tag; without one the placeholder resolves to "dev".
VERSION="${1:-}"
VERSION_FILE="src/Version.php"
VERSION_BACKUP=""

# Box is fetched out-of-band so it never lands in vendor/ and is never bundled into the phar.
BOX_URL="https://github.com/box-project/box/releases/latest/download/box.phar"
BOX_PHAR="$(mktemp -t box-XXXXXX.phar)"

CYAN='\033[0;36m'
GREEN='\033[0;32m'
RESET='\033[0m'

info() {
    echo -e "${CYAN}$1${RESET}"
}

cleanup() {
    rm -f "$BOX_PHAR"

    # Restore the version placeholder if the build was interrupted after we replaced it.
    if [ -n "$VERSION_BACKUP" ] && [ -f "$VERSION_BACKUP" ]; then
        mv "$VERSION_BACKUP" "$VERSION_FILE"
    fi
}
trap cleanup EXIT

if [ -n "$VERSION" ]; then
    # Bake the version into the binary, mirroring Laravel Zero's app:build --build-version.
    info "Baking version $VERSION..."
    VERSION_BACKUP="${VERSION_FILE}.bak"
    cp "$VERSION_FILE" "$VERSION_BACKUP"
    sed "s/@git_version@/${VERSION}/" "$VERSION_BACKUP" > "$VERSION_FILE"
fi

info "Downloading Box..."
curl -sSL -o "$BOX_PHAR" "$BOX_URL"

info "Building binary..."
php -d phar.readonly=0 "$BOX_PHAR" compile

if [ -n "$VERSION_BACKUP" ]; then
    # Restore the source placeholder now that the version is baked into the phar.
    mv "$VERSION_BACKUP" "$VERSION_FILE"
    VERSION_BACKUP=""
fi

info "Built builds/cpx"
