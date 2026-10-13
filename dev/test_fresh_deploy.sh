#!/usr/bin/env bash
# Simuleaza deploy-ul pe o baza GOALA (ca pe un server nou), intr-o baza separata de test,
# si verifica ca toate migrarile supravietuiesc importului din productie_date.sql.
# Ruleaza db/deploy_db.php de doua ori (a doua oara trebuie sa nu strice nimic).
#
#   bash dev/test_fresh_deploy.sh [coloana_tabel ...]   ex: products.price_is_from
set -u
cd "$(dirname "$0")/.."
PHP="${PHP:-/c/Users/lucia/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.NTS.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe}"
MYSQL="/c/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin/mysql.exe -u root -h 127.0.0.1"
DB=mugurel_cms_fresh
TMP=$(mktemp -d); mkdir -p "$TMP/admin"
trap '$MYSQL -e "DROP DATABASE IF EXISTS $DB"; rm -rf "$TMP"' EXIT
fail=0

cat > "$TMP/admin/config.local.php" <<EOF
<?php
define('DB_HOST', '127.0.0.1'); define('DB_NAME', '$DB'); define('DB_USER', 'root'); define('DB_PASS', '');
EOF

$MYSQL -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQL $DB < db/schema.sql
$MYSQL $DB -e "INSERT INTO users (id, username, email, password_hash, role) VALUES (1,'admin','a@local.test','x','admin');"

for run in 1 2; do
    out=$("$PHP" db/deploy_db.php "$TMP" 2>&1)
    errs=$(echo "$out" | grep -c "EROARE")
    n=$(echo "$out" | grep -oE "Produse: [0-9]+")
    imp=$(echo "$out" | grep -oE "Import date: [a-z]+")
    echo "rulare $run: $imp, $n, erori=$errs"
    [ "$errs" = 0 ] || { echo "$out" | grep EROARE; fail=1; }
    [ "$n" = "Produse: 534" ] || fail=1
    # Verificam dupa FIECARE rulare: pe un server nou, site-ul functioneaza dupa primul deploy.
    for spec in "$@"; do
        t=${spec%%.*}; c=${spec#*.}
        found=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='$t' AND COLUMN_NAME='$c'")
        [ "$found" = 1 ] && echo "ok   rulare $run: coloana $spec exista" || { echo "FAIL rulare $run: coloana $spec lipseste"; fail=1; }
    done
done

[ $fail -eq 0 ] && echo "=== DEPLOY PE BAZA GOALA: OK ===" || echo "=== DEPLOY PE BAZA GOALA: ESEC ==="
exit $fail
