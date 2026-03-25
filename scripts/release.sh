#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" = "" ]; then
  echo "Usage: scripts/release.sh <version> [release notes]"
  exit 1
fi

VERSION="$1"
NOTES="${2:-Release v$VERSION}"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/wp-plugin/flight-schedule-timetable/flight-schedule-timetable.php"
ZIP_FILE="$ROOT_DIR/flight-schedule-timetable-plugin.zip"
REPO_SLUG="jkhliffz09/flight-schedule-timetable"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Version must look like 1.2.3"
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI (gh) is required."
  exit 1
fi

if ! git -C "$ROOT_DIR" diff --quiet || ! git -C "$ROOT_DIR" diff --cached --quiet; then
  echo "Working tree must be clean before releasing."
  exit 1
fi

perl -0pi -e "s/\\* Version: [0-9]+\\.[0-9]+\\.[0-9]+/* Version: $VERSION/g; s/const VERSION = '[0-9]+\\.[0-9]+\\.[0-9]+';/const VERSION = '$VERSION';/g" "$PLUGIN_FILE"

npm --prefix "$ROOT_DIR" version --no-git-tag-version "$VERSION" >/dev/null
npm --prefix "$ROOT_DIR" run build

rm -f "$ZIP_FILE"
(
  cd "$ROOT_DIR/wp-plugin"
  zip -r ../flight-schedule-timetable-plugin.zip flight-schedule-timetable -x '*.DS_Store' -x '__MACOSX/*' >/dev/null
)

git -C "$ROOT_DIR" add "$PLUGIN_FILE" "$ROOT_DIR/package.json" "$ROOT_DIR/package-lock.json"
git -C "$ROOT_DIR" commit -m "Release v$VERSION"
git -C "$ROOT_DIR" tag "v$VERSION"
git -C "$ROOT_DIR" push origin main
git -C "$ROOT_DIR" push origin "v$VERSION"

gh release create "v$VERSION" "$ZIP_FILE" \
  --repo "$REPO_SLUG" \
  --title "v$VERSION" \
  --notes "$NOTES"

echo "Release v$VERSION published."
