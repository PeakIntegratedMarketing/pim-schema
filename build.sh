#!/usr/bin/env bash
# Builds dist/pim-schema.zip (top-level folder "pim-schema/") for upload via Plugins → Add New → Upload.
# Runs the tests first; a failing test stops the build.
set -euo pipefail

cd "$(dirname "$0")"
PHP="${PHP:-/opt/homebrew/bin/php}"
for f in pim-schema.php includes/*.php includes/faq/*.php includes/entities/*.php includes/types/*.php; do "$PHP" -l "$f" >/dev/null; done
"$PHP" tests/extractor.test.php >/dev/null
"$PHP" tests/entities.test.php >/dev/null
"$PHP" tests/post-types.test.php >/dev/null
node tests/faq-parse.test.js >/dev/null
node --check assets/faq-editor.js
node --check assets/entities-panel.js

rm -rf dist
mkdir -p dist/pim-schema
rsync -a --exclude-from=.distignore ./ dist/pim-schema/
(cd dist && zip -qr pim-schema.zip pim-schema && rm -rf pim-schema)
echo "Built dist/pim-schema.zip ($(grep -m1 'Version:' pim-schema.php | awk '{print $NF}'))"
