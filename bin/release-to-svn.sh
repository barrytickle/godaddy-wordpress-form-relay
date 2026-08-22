#!/usr/bin/env bash
# Sync the git repo's shipped files into the WordPress.org SVN working copy,
# cut a tag for the current version, and (after confirmation) commit.
#
# Does NOT touch assets/ (icon, banner, screenshots) - those aren't tracked
# in git and are managed by hand in the SVN working copy directly.
#
# Usage:
#   bin/release-to-svn.sh            # sync + tag, ask before committing
#   bin/release-to-svn.sh --no-commit  # sync + tag, stop before the commit step
#
# Env vars:
#   SVN_USER  WordPress.org username (default: tangodevelopment)

set -euo pipefail

SVN_URL="https://plugins.svn.wordpress.org/tango-form-wire"
SVN_USER="${SVN_USER:-tangodevelopment}"
NO_COMMIT=0
[ "${1:-}" = "--no-commit" ] && NO_COMMIT=1

command -v svn >/dev/null || { echo "svn not found. Install with: brew install subversion" >&2; exit 1; }
command -v rsync >/dev/null || { echo "rsync not found." >&2; exit 1; }

REPO_ROOT="$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)"
SVN_DIR="$REPO_ROOT/.svn-wc"

if ! git -C "$REPO_ROOT" diff --quiet || ! git -C "$REPO_ROOT" diff --cached --quiet; then
    echo "Warning: uncommitted changes in the git working tree - the release will still" >&2
    echo "be built from the files on disk right now, not the last git commit." >&2
fi

MAIN_FILE="$REPO_ROOT/tango-form-wire.php"
README_FILE="$REPO_ROOT/readme.txt"

PLUGIN_VERSION="$(grep -oE 'Version:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+' "$MAIN_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')"
STABLE_TAG="$(grep -oE 'Stable tag:[[:space:]]*[0-9]+\.[0-9]+\.[0-9]+' "$README_FILE" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')"

if [ "$PLUGIN_VERSION" != "$STABLE_TAG" ]; then
    echo "Version mismatch: tango-form-wire.php says $PLUGIN_VERSION, readme.txt Stable tag says $STABLE_TAG." >&2
    echo "Fix that before releasing." >&2
    exit 1
fi

echo "Releasing version $PLUGIN_VERSION"

if [ ! -d "$SVN_DIR/.svn" ]; then
    echo "No local SVN working copy found, checking out..."
    svn checkout "$SVN_URL" "$SVN_DIR"
else
    echo "Updating existing SVN working copy..."
    svn update "$SVN_DIR"
fi

echo "Syncing shipped files into trunk..."
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

cd "$REPO_ROOT"
git ls-files | grep -v -E '^(AGENTS\.md|README\.md|NOTES\.md|RELEASING\.md|human\.txt)$' | while IFS= read -r f; do
    mkdir -p "$STAGE/$(dirname "$f")"
    cp "$REPO_ROOT/$f" "$STAGE/$f"
done

rsync -a --delete --exclude='.svn' "$STAGE/" "$SVN_DIR/trunk/"

cd "$SVN_DIR"
svn add --force trunk >/dev/null 2>&1 || true
svn status trunk | awk '/^!/ {print $2}' | while IFS= read -r missing; do
    svn rm --force "$missing" >/dev/null
done

TAG_PATH="tags/$PLUGIN_VERSION"
if svn info "$SVN_URL/$TAG_PATH" >/dev/null 2>&1; then
    echo "Tag $PLUGIN_VERSION already exists on WordPress.org - not re-tagging."
    echo "(If you meant to cut a new release, bump the version number first.)"
else
    echo "Cutting tag $PLUGIN_VERSION..."
    svn cp trunk "$TAG_PATH"
fi

echo
echo "=== svn status ==="
svn status
echo

if [ "$NO_COMMIT" -eq 1 ]; then
    echo "Stopped before commit (--no-commit). Review the above, then run:"
    echo "  cd \"$SVN_DIR\" && svn commit -m \"Release $PLUGIN_VERSION\" --username $SVN_USER"
    exit 0
fi

read -r -p "Commit and publish version $PLUGIN_VERSION to WordPress.org now? [y/N] " confirm
if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
    svn commit -m "Release $PLUGIN_VERSION" --username "$SVN_USER"
else
    echo "Not committed. Working copy at $SVN_DIR still has everything staged for next time."
fi
