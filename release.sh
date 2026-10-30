#!/usr/bin/env bash
# Publishes the current version as a GitHub release with dist/pim-schema.zip attached.
# Sites then show "Update available" on their Plugins screen (checked about every 12 hours,
# or right away via Dashboard → Updates → Check again).
#   ./release.sh            → normal release (all sites see it)
#   ./release.sh --pre      → pre-release (sites ignore it; install the zip on staging by hand)
# Commit and push first. Notes come from the README changelog line of this version.
set -euo pipefail
cd "$(dirname "$0")"
GH="${GH:-/opt/homebrew/bin/gh}"
V=$(grep -m1 'Version:' pim-schema.php | awk '{print $NF}')
[ -z "$(git status --porcelain)" ] || { echo "Uncommitted changes; commit first." >&2; exit 1; }
./build.sh
NOTES=$(grep -m1 -F -- "- **$V**" README.md || echo "v$V")
git rev-parse "v$V" >/dev/null 2>&1 || git tag -a "v$V" -m "v$V"
git push -q && git push -q origin "v$V"
"$GH" release create "v$V" dist/pim-schema.zip --title "v$V" --notes "$NOTES" ${1:+--prerelease}
