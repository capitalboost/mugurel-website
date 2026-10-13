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

# Fixturile de cos (produse cu pret fix) — doar in baza locala, idempotente.
/c/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin/mysql.exe -u root -h 127.0.0.1 mugurel_cms < "$SRC/../fixtures/cos_test.sql"

cd "$RUNNER" && npx playwright test "$@"
