#!/usr/bin/env bash

set -e

# Run from the repository root so box.json and the source paths resolve.
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

# An optional version bakes an explicit tag; without one the placeholder resolves to "dev".
VERSION="${1:-}"
VERSION_FILE="src/Version.php"
VERSION_BACKUP=""

RUNTIME_PACKAGES=("composer/composer" "composer/semver" "laravel/agent-detector" "laravel/prompts" "symfony/console" "symfony/process")
COMPOSER_JSON_BACKUP=""
COMPOSER_LOCK_BACKUP=""

# The pinned copy is cached under builds/ (gitignored) and only downloaded once.
BOX_VERSION="4.7.0"
BOX_SHA256="3d390eeaec33288098fe83f8a54c60cc575cb6be295f38ff4482b4b4f26f8d52"
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
    # Restore anything we swapped out if the build was interrupted mid-flight.
    restored=0
    if [ -n "$VERSION_BACKUP" ] && [ -f "$VERSION_BACKUP" ]; then
        mv "$VERSION_BACKUP" "$VERSION_FILE"
    fi
    if [ -n "$COMPOSER_JSON_BACKUP" ] && [ -f "$COMPOSER_JSON_BACKUP" ]; then
        mv "$COMPOSER_JSON_BACKUP" composer.json
        restored=1
    fi
    if [ -n "$COMPOSER_LOCK_BACKUP" ] && [ -f "$COMPOSER_LOCK_BACKUP" ]; then
        mv "$COMPOSER_LOCK_BACKUP" composer.lock
        restored=1
    fi
    if [ "$restored" -eq 1 ]; then
        echo "Restored composer.json and composer.lock; run \`composer install\` to reinstall dev dependencies." >&2
    fi
}
trap cleanup EXIT

# Promote the runtime packages into "require" and drop "require-dev" so a --no-dev install
# resolves only the runtime closure. composer.json/lock are restored before the script exits.
info "Installing production dependencies..."
COMPOSER_JSON_BACKUP="composer.json.bak"
COMPOSER_LOCK_BACKUP="composer.lock.bak"
cp composer.json "$COMPOSER_JSON_BACKUP"
cp composer.lock "$COMPOSER_LOCK_BACKUP"

php -r '
    $path = "composer.json";
    $data = json_decode(file_get_contents($path), true);
    $lock = json_decode(file_get_contents("composer.lock"), true);
    $locked = [];
    foreach (array_merge($lock["packages"] ?? [], $lock["packages-dev"] ?? []) as $package) {
        $locked[$package["name"]] = $package["version"];
    }
    foreach (array_slice($argv, 1) as $package) {
        if (! isset($data["require-dev"][$package])) {
            fwrite(STDERR, "Runtime package not found in require-dev: {$package}\n");
            exit(1);
        }
        if (! isset($locked[$package])) {
            fwrite(STDERR, "Runtime package not found in composer.lock: {$package}\n");
            exit(1);
        }
        $data["require"][$package] = $locked[$package];
    }
    unset($data["require-dev"]);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
' "${RUNTIME_PACKAGES[@]}"

composer update --no-dev --no-interaction --quiet

if [ ! -f "$BOX_PHAR" ]; then
    info "Downloading Box ${BOX_VERSION}..."
    mkdir -p builds
    curl -sSL -o "${BOX_PHAR}.tmp" "$BOX_URL"
    mv "${BOX_PHAR}.tmp" "$BOX_PHAR"
fi

if command -v sha256sum >/dev/null 2>&1; then
    SHA256_CMD="sha256sum"
else
    SHA256_CMD="shasum -a 256"
fi

if ! echo "${BOX_SHA256}  ${BOX_PHAR}" | $SHA256_CMD -c --status -; then
    rm -f "$BOX_PHAR"
    echo "Box ${BOX_VERSION} failed the sha256 check; expected ${BOX_SHA256}. The download was discarded." >&2
    exit 1
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

# Restore the committed manifest and reinstall the dev dependencies for local work.
info "Restoring development dependencies..."
mv "$COMPOSER_JSON_BACKUP" composer.json
COMPOSER_JSON_BACKUP=""
mv "$COMPOSER_LOCK_BACKUP" composer.lock
COMPOSER_LOCK_BACKUP=""
composer install --no-interaction --quiet

success "Built builds/cpx"
