#!/usr/bin/env bash
# Simuleaza db/deploy_db.php (pasul din .cpanel.yml) in doua scenarii, fiecare intr-o baza de test separata:
#
#   A. server NOU: baza goala (schema.sql + un admin) => migrarile + catalogul nou din db/catalog/*.xlsx
#   B. LANSAREA pe productie: copia bazei live din 16 oct (mugurel_live_20261016, 534 produse vechi)
#      => catalogul vechi inlocuit cu cel nou, adresele vechi pastrate (acoperis, incalzire, electrocasnice)
#
# In ambele: a doua rulare (urmatorul deploy) nu mai atinge catalogul, iar o modificare facuta „din admin"
# intre rulari ramane. Baza locala mugurel_cms nu e atinsa.
#
#   bash dev/test_fresh_deploy.sh
set -u
cd "$(dirname "$0")/.."
PHP="${PHP:-/c/Users/lucia/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.NTS.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe}"
BIN=/c/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin
MYSQL="$BIN/mysql.exe -u root -h 127.0.0.1"
DB=mugurel_cms_deploytest
LIVE=mugurel_live_20261016
TMP=$(mktemp -d); mkdir -p "$TMP/admin"
trap '$MYSQL -e "DROP DATABASE IF EXISTS $DB"; rm -rf "$TMP"' EXIT
fail=0
EXPECTED=$(ls db/catalog/*.xlsx | wc -l)

cat > "$TMP/admin/config.local.php" <<EOF
<?php
define('DB_HOST', '127.0.0.1'); define('DB_NAME', '$DB'); define('DB_USER', 'root'); define('DB_PASS', '');
EOF

q() { $MYSQL -N $DB -e "$1"; }
check() { # check "descriere" valoare_obtinuta valoare_asteptata
    if [ "$2" = "$3" ]; then echo "ok   $1 ($2)"; else echo "FAIL $1: $2 (asteptat $3)"; fail=1; fi
}
deploy() { # deploy <eticheta>
    out=$("$PHP" db/deploy_db.php "$TMP" 2>&1)
    errs=$(echo "$out" | grep -c "EROARE")
    echo "--- $1: $(echo "$out" | grep -oE 'Import catalog: [a-z]+'), $(echo "$out" | grep -oE 'Produse: [0-9]+'), erori=$errs"
    [ "$errs" = 0 ] || { echo "$out" | grep EROARE; fail=1; }
}
scenario_checks() { # verificari comune dupa importul catalogului
    check "produse vechi (fara cod de bare)" "$(q "SELECT COUNT(*) FROM products WHERE barcode IS NULL")" 0
    check "produse noi" "$(q "SELECT COUNT(*) FROM products WHERE barcode IS NOT NULL")" 511
    check "categorii fara produse si fara subcategorii" "$(q "SELECT COUNT(*) FROM categories c LEFT JOIN products p ON p.category_id=c.id LEFT JOIN categories ch ON ch.parent_id=c.id WHERE p.id IS NULL AND ch.id IS NULL")" 0
    check "/acoperis.html ramane: slug acoperis = Materiale constructii > Acoperis" "$(q "SELECT p.slug FROM categories c JOIN categories p ON p.id=c.parent_id WHERE c.slug='acoperis'")" materiale-constructii
    check "/incalzire.html ramane: ramura Incalzire" "$(q "SELECT COUNT(*) FROM categories WHERE slug='incalzire' AND parent_id IS NULL")" 1
    check "/electrocasnice.html ramane: ramura Electrocasnice" "$(q "SELECT COUNT(*) FROM categories WHERE slug='electrocasnice' AND parent_id IS NULL")" 1
    check "colectii" "$(q "SELECT COUNT(*) FROM collections")" 5
    check "tabelele cosului (migrarea 010)" "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME IN ('orders','order_items','order_attempts')")" 3
}
redeploy_keeps_admin_edits() {
    # „Mugurel" schimba un pret, stocul si ascunde un produs din admin; urmatorul deploy nu trebuie sa le atinga
    q "UPDATE products SET price = 99.99, stock_status='la_comanda' WHERE barcode='5946043001367'; UPDATE products SET is_visible=0 WHERE barcode='5945762000019'"
    deploy "$1"
    check "redeploy: catalog sarit, tot 511" "$(q "SELECT COUNT(*) FROM products")" 511
    check "redeploy: pretul schimbat din admin ramane" "$(q "SELECT CONCAT(price,'/',stock_status) FROM products WHERE barcode='5946043001367'")" "99.99/la_comanda"
    check "redeploy: produsul ascuns ramane ascuns" "$(q "SELECT is_visible FROM products WHERE barcode='5945762000019'")" 0
}

echo "=== A. server nou (baza goala) — $EXPECTED fisiere in db/catalog ==="
$MYSQL -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQL $DB < db/schema.sql
q "INSERT INTO users (id, username, email, password_hash, role) VALUES (1,'admin','a@local.test','x','admin');"
deploy "A rularea 1"
scenario_checks
redeploy_keeps_admin_edits "A rularea 2"

echo "=== B. lansarea: copia bazei live din 16 oct ($LIVE) ==="
if [ "$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$LIVE'")" != 1 ]; then
    echo "SKIP: baza $LIVE nu exista local (vezi docs/JURNAL-LUCRU.md, 16 oct — importul backup-ului cPanel)"
else
    $MYSQL -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    "$BIN/mysqldump.exe" -u root -h 127.0.0.1 $LIVE | $MYSQL $DB
    check "inainte: produse vechi pe live" "$(q "SELECT COUNT(*) FROM products")" 534
    deploy "B rularea 1 (lansarea)"
    scenario_checks
    redeploy_keeps_admin_edits "B rularea 2"
fi

[ $fail -eq 0 ] && echo "=== DEPLOY: OK ===" || echo "=== DEPLOY: ESEC ==="
exit $fail
