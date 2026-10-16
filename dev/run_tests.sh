#!/usr/bin/env bash
# Ruleaza toate testele PHP din admin/tests si verificarea paginilor pe serverul local.
# Iese cu cod != 0 daca orice test sau orice verificare pica.
#
#   bash dev/run_tests.sh            # teste PHP + verificare pagini
#   bash dev/run_tests.sh --php      # doar teste PHP
set -u
cd "$(dirname "$0")/.."

PHP="${PHP:-/c/Users/lucia/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.NTS.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe}"
BASE="${BASE:-http://localhost:8080}"
fail=0; passed=0

# Fixturile locale (stari de stoc, pret cu zecimale, curatarea comenzilor de test) — idempotente.
/c/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin/mysql.exe -u root -h 127.0.0.1 mugurel_cms < dev/fixtures/cos_test.sql

for t in admin/tests/test_*.php; do
    out=$("$PHP" "$t" 2>&1); code=$?
    line=$(echo "$out" | grep -E 'Rezultat: [0-9]+ passed, [0-9]+ failed' | tail -1)
    p=$(echo "$line" | sed -E 's/.*: ([0-9]+) passed.*/\1/'); f=$(echo "$line" | sed -E 's/.* ([0-9]+) failed.*/\1/')
    if [ $code -ne 0 ] || [ -z "$line" ] || [ "$f" != "0" ]; then
        echo "FAIL $t (exit=$code) ${line:-fara linie de rezultat}"; echo "$out" | grep -E '✗|Fatal|Error|Warning' | head -20; fail=1
    else
        echo "ok   $t — $p passed"; passed=$((passed + p))
    fi
done
echo "PHP: $passed asertiuni trecute"

# Sintaxa PHP 8.1 pe tot codul (productia ruleaza 8.1.34)
lint_fail=0
while IFS= read -r f; do
    "$PHP" -l "$f" >/dev/null 2>&1 || { echo "LINT FAIL $f"; "$PHP" -l "$f"; lint_fail=1; }
done < <(git ls-files '*.php'; git ls-files --others --exclude-standard '*.php')
[ $lint_fail -eq 0 ] && echo "ok   php -l pe toate fisierele" || fail=1

[ "${1:-}" = "--php" ] && exit $fail

# Regresie pe pagini (catalogul nou, 16 oct): fiecare categorie si colectie din sitemap raspunde 200, are carduri
# si date structurate; totalul din catalog = produsele vizibile din baza.
MYSQL_N="/c/Users/lucia/AppData/Local/mugurel-dev/mariadb/bin/mysql.exe -u root -h 127.0.0.1 -N mugurel_cms"
expected=$($MYSQL_N -e "SELECT COUNT(*) FROM products WHERE is_visible = 1 AND barcode IS NOT NULL")
pages=0; bad=0
for u in $(curl -s "$BASE/sitemap.xml" | grep -o '<loc>[^<]*' | sed 's#<loc>https://mugurel-bricolaj.ro##' | grep -v '^/produs/' | grep -v '^/$\|contact\|despre-noi'); do
    code=$(curl -s -o /tmp/pg.html -w '%{http_code}' "$BASE$u")
    cards=$(grep -o 'class="prod-card cat-card"' /tmp/pg.html | wc -l)
    j=$(grep -c 'application/ld+json' /tmp/pg.html)
    pages=$((pages + 1))
    if [ "$code" != 200 ] || [ "$cards" -lt 1 ] || [ "$j" -lt 1 ]; then echo "FAIL $u: http=$code carduri=$cards jsonld=$j"; bad=1; fi
done
[ $bad -eq 0 ] && echo "ok   $pages liste (catalog, categorii, colectii): 200, cu produse si JSON-LD" || fail=1
cat_n=$(curl -s "$BASE/catalog.html" | grep -o '<strong>[0-9]*</strong> produse' | grep -o '[0-9]*' | head -1)
[ "$cat_n" = "$expected" ] && echo "ok   catalog: $cat_n produse (= baza)" || { echo "FAIL catalog: '$cat_n' (asteptat $expected)"; fail=1; }
for slug in $($MYSQL_N -e "SELECT slug FROM products WHERE is_visible = 1 AND barcode IS NOT NULL ORDER BY RAND() LIMIT 25"); do
    c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/produs/$slug.html"); [ "$c" = 200 ] || { echo "FAIL /produs/$slug.html -> $c"; fail=1; }
done
echo "ok   25 pagini de produs luate la intamplare: 200"
for u in /acoperis.html /electrocasnice.html; do
    c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u"); [ "$c" = 200 ] || { echo "FAIL adresa veche $u -> $c (trebuia categoria noua)"; fail=1; }
done
for u in /izolatie.html /mobilier.html /electrice.html; do
    c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u"); [ "$c" = 301 ] || { echo "FAIL adresa veche $u -> $c (trebuia 301)"; fail=1; }
done
echo "ok   adresele vechi: categorii pastrate sau 301"
for u in / /index.html /contact.html /despre-noi.html /catalog.html /cos.html /comanda.html /sitemap.xml; do
    c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u"); [ "$c" = 200 ] || { echo "FAIL $u -> $c"; fail=1; }
done
c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/helpers/Csrf.php"); [ "$c" = 403 ] || { echo "FAIL admin/helpers accesibil ($c)"; fail=1; }
echo "ok   pagini statice + protectie admin verificate"

[ $fail -eq 0 ] && echo "=== TOATE VERIFICARILE AU TRECUT ===" || echo "=== EXISTA ESECURI ==="
exit $fail
