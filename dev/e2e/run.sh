#!/usr/bin/env bash
# Ruleaza testele Playwright. node_modules stau in afara Google Drive (sincronizarea
# lor ar dura zeci de minute), asa ca testele se copiaza intr-un runner local.
#
#   bash dev/e2e/run.sh [argumente playwright, ex: --project=mobil tests/cos.spec.js]
set -eu
SRC="$(cd "$(dirname "$0")" && pwd)"
RUNNER="${E2E_RUNNER:-/c/Users/lucia/AppData/Local/mugurel-dev/e2e}"

mkdir -p "$RUNNER"
cp "$SRC/package.json" "$SRC/playwright.config.js" "$RUNNER/"
rm -rf "$RUNNER/tests" && cp -R "$SRC/tests" "$RUNNER/tests"
[ -d "$RUNNER/node_modules/@playwright/test" ] || (cd "$RUNNER" && npm install --no-audit --no-fund && npx playwright install chromium)

cd "$RUNNER" && npx playwright test "$@"
