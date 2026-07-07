#!/usr/bin/env bash

set -e

# Run from the repository root so builds/ and scripts/ paths resolve.
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

RELEASE_BRANCH="2.x"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

abort() {
    echo -e "${RED}Error: $1${RESET}" >&2
    exit 1
}

info() {
    echo -e "${CYAN}$1${RESET}"
}

success() {
    echo -e "${GREEN}$1${RESET}"
}

# Must be on the release branch.
BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$BRANCH" != "$RELEASE_BRANCH" ]; then
    abort "You must be on the '$RELEASE_BRANCH' branch to release. Currently on: $BRANCH"
fi

info "Fetching latest remote state..."
git fetch --tags origin

# Working tree must be clean before we build and commit the versioned phar.
if [ -n "$(git status --porcelain)" ]; then
    abort "You have uncommitted changes or untracked files. Please clean up before releasing."
fi

# Local branch must be in sync with its remote.
AHEAD=$(git rev-list "origin/${RELEASE_BRANCH}..HEAD" --count)
BEHIND=$(git rev-list "HEAD..origin/${RELEASE_BRANCH}" --count)

if [ "$AHEAD" -gt 0 ]; then
    abort "Your branch is $AHEAD commit(s) ahead of origin/${RELEASE_BRANCH}. Push before releasing."
fi

if [ "$BEHIND" -gt 0 ]; then
    abort "Your branch is $BEHIND commit(s) behind origin/${RELEASE_BRANCH}. Pull before releasing."
fi

# Determine the current latest tag.
CURRENT_TAG=$(git tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | head -1)

if [ -z "$CURRENT_TAG" ]; then
    CURRENT_TAG="v0.0.0"
    info "No existing tags found. Starting from $CURRENT_TAG."
else
    info "Current latest tag: ${BOLD}$CURRENT_TAG${RESET}"
fi

VERSION="${CURRENT_TAG#v}"
MAJOR=$(echo "$VERSION" | cut -d. -f1)
MINOR=$(echo "$VERSION" | cut -d. -f2)
PATCH=$(echo "$VERSION" | cut -d. -f3)

NEXT_PATCH="v${MAJOR}.${MINOR}.$((PATCH + 1))"
NEXT_MINOR="v${MAJOR}.$((MINOR + 1)).0"
NEXT_MAJOR="v$((MAJOR + 1)).0.0"

echo ""
echo -e "${BOLD}Select version bump type:${RESET}"
echo -e "  1) patch  → ${NEXT_PATCH}"
echo -e "  2) minor  → ${NEXT_MINOR}"
echo -e "  3) major  → ${NEXT_MAJOR}"
echo ""

while true; do
    read -r -p "Choice [1/2/3]: " CHOICE

    case "$CHOICE" in
        1|patch) NEW_TAG="$NEXT_PATCH"; break ;;
        2|minor) NEW_TAG="$NEXT_MINOR"; break ;;
        3|major) NEW_TAG="$NEXT_MAJOR"; break ;;
        *) echo -e "${YELLOW}Please enter 1, 2, or 3.${RESET}" ;;
    esac
done

echo ""
echo -e "New tag will be: ${BOLD}${GREEN}${NEW_TAG}${RESET}"
echo ""

read -r -p "Confirm and release $NEW_TAG? [y/N] " CONFIRM

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

info "Building $NEW_TAG..."
bash scripts/build.sh "$NEW_TAG"

info "Smoke testing binary..."

if [ ! -f "builds/cpx" ]; then
    abort "Build output builds/cpx not found."
fi

SMOKE_VERSION=$(./builds/cpx --version 2>&1) || abort "Binary failed to execute: $SMOKE_VERSION"

if ! echo "$SMOKE_VERSION" | grep -qF "$NEW_TAG"; then
    abort "Binary reported unexpected version. Expected '$NEW_TAG', got: $SMOKE_VERSION"
fi

./builds/cpx list >/dev/null 2>&1 || abort "Binary failed to list — bundled dependencies may be broken."

# Prove the bundled Composer runs from inside the phar by installing and executing a package.
SMOKE_HOME=$(mktemp -d)
CPX_HOME="$SMOKE_HOME" ./builds/cpx friendsofphp/php-cs-fixer --version >/dev/null 2>&1 \
    || { rm -rf "$SMOKE_HOME"; abort "Binary failed to install and run a package through the bundled Composer."; }
rm -rf "$SMOKE_HOME"

success "Smoke test passed: $SMOKE_VERSION"

# Commit the versioned phar so 'composer global require/update cpx/cpx' serves it as the bin.
info "Committing build..."
git add builds/cpx
git commit -m "Build $NEW_TAG"
git push origin "$RELEASE_BRANCH"

# Create the GitHub release; this also creates the tag that Packagist publishes.
info "Creating release $NEW_TAG..."
gh release create "$NEW_TAG" builds/cpx --title "$NEW_TAG" --target "$RELEASE_BRANCH" --generate-notes

REPO_URL=$(gh repo view --json url -q .url)

echo ""
success "Release $NEW_TAG created."
echo ""
echo -e "  Release:  ${CYAN}${REPO_URL}/releases/tag/${NEW_TAG}${RESET}"
echo ""
