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

# Regresie pe pagini: cifrele din jurnal (231 materiale + 264 accesorii, catalog 534)
tm=0; ta=0
for p in acoperis izolatie gips-carton zidarie-bca gard-imprejmuiri electrice incalzire \
         sanitare apa-canal gradina mobilier electrocasnice scule-unelte; do
    code=$(curl -s -o /tmp/pg.html -w '%{http_code}' "$BASE/$p.html")
    m=$(grep -o 'class="material-card"' /tmp/pg.html | wc -l); a=$(grep -o 'class="accessory-card' /tmp/pg.html | wc -l)
    j=$(grep -c 'application/ld+json' /tmp/pg.html)
    tm=$((tm + m)); ta=$((ta + a))
    if [ "$code" != 200 ] || [ "$j" -lt 1 ]; then echo "FAIL pagina $p: http=$code jsonld=$j"; fail=1; fi
done
[ $tm -eq 231 ] && [ $ta -eq 264 ] && echo "ok   13 pagini: $tm materiale + $ta accesorii" \
    || { echo "FAIL carduri: $tm materiale + $ta accesorii (asteptat 231 + 264)"; fail=1; }
cat_n=$(curl -s "$BASE/catalog.html" | grep -o 'din [0-9]* produse' | head -1)
[ "$cat_n" = "din 534 produse" ] && echo "ok   catalog: $cat_n" || { echo "FAIL catalog: '$cat_n'"; fail=1; }
for u in / /index.html /contact.html /catalog.html; do
    c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u"); [ "$c" = 200 ] || { echo "FAIL $u -> $c"; fail=1; }
done
c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/helpers/Csrf.php"); [ "$c" = 403 ] || { echo "FAIL admin/helpers accesibil ($c)"; fail=1; }
echo "ok   pagini statice + protectie admin verificate"

[ $fail -eq 0 ] && echo "=== TOATE VERIFICARILE AU TRECUT ===" || echo "=== EXISTA ESECURI ==="
exit $fail
