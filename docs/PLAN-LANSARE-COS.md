# Plan de lansare in productie — cos si comenzi online

**Stare:** pregatit, **NEEXECUTAT**. Nimic din acest plan nu se face fara aprobarea explicita a lui Lucian.
**Branch:** `feat/cos-comenzi` (pornit din `feat/catalog-db`, ramura de pe care ruleaza azi productia).
**Ce se lanseaza:** pret fix pe toate produsele, indicator de stoc, cos, comanda PF/PJ (ramburs / transfer / ridicare),
salvarea comenzilor, email intern + email de confirmare pentru client, pagina de confirmare.
Detaliile si testele fiecarui pas: `IMPLEMENTATION_PROGRESS.md` (T1–T15).

---

## A. Conditii obligatorii INAINTE de lansare (blocante)

| # | Ce | Cine | De ce blocheaza |
|---|---|---|---|
| A1 | **Preturile reale, confirmate**, in Excel (sablon: `Fisiere de lucru/export_preturi.py`; coloane `ID`, `Pret (lei, cu TVA)`, `Unitate`) | Mugurel | Azi local sunt **estimari** („Estimare euristica… NU oferta verificata"). Pe site ar deveni preturi la care clientul comanda. |
| A2 | **Stocul real**: produsele care nu se pot livra marcate „Indisponibil" sau „La comanda" din admin | Mugurel | Implicit toate sunt „In stoc" (decizia din 13 oct). |
| A3 | **Textele** de plata, transport si din emailuri citite si aprobate (`includes/order_form.php`: `ORDER_PLATA`, `ORDER_TRANSPORT_INFO`; `includes/order_mail.php`) | Mugurel / Lucian | Promit lucruri in numele magazinului (ex. card la ridicare, proforma pe email). |
| A4 | **Cont de email pentru trimitere**: casuta `comenzi@mugurel-bricolaj.ro` in cPanel (sau alta), cu datele SMTP | Lucian | Fara SMTP real nu pleaca niciun email (comenzile se salveaza, dar nimeni nu e anuntat). |
| A5 | **DNS pentru email** in Cloudflare: SPF, DKIM (din cPanel → Email Deliverability), DMARC | Lucian | Altfel emailurile catre clienti ajung in Spam sau sunt respinse. |
| A6 | **Pagini legale actualizate** pentru vanzarea la distanta: Termeni (comanda online, pret cu TVA, plata, livrare, **dreptul de retragere de 14 zile** — OUG 34/2014, cu exceptiile pentru marfa la comanda/taiata la dimensiune), legaturi ANPC (SAL) si platforma europeana SOL; Politica de confidentialitate (ce date se colecteaza la comanda, de ce, cat se pastreaza) | Lucian / un jurist | Obligatii legale pentru comertul online cu persoane fizice. *Aceasta lista nu inlocuieste un avocat.* |

## B. Recomandate (nu blocheaza, dar merita facute odata cu lansarea)

- **B1. Testele din `admin/tests/` sa nu mai ajunga pe server.** `.cpanel.yml` copiaza tot `admin/`, deci si testele (unele scriu in baza).
  Sunt blocate de `admin/.htaccess` (403), dar e mai sigur sa nu existe acolo. Adauga in `.cpanel.yml`, dupa copierea lui `admin`:
  `- /bin/rm -rf $DEPLOYPATH/admin/tests`
- **B2. Limitarea login-ului din admin dupa IP-ul real** (jurnal, punctul 3): `orderClientIp()` din `includes/order_security.php` rezolva exact problema.
- **B3. Parola de admin din `db/seed_admin.php`** (jurnal, punctul 1) — schimbata pe live si scoasa din git.
- **B4. Textele meta din `catalog.php`** spun „Pret la cerere pe WhatsApp" — de actualizat acum ca exista preturi.
- **B5. Harta de pe `contact.html`** blocata de CSP (jurnal, punctul 11): `frame-src https://maps.google.com https://www.google.com`.
- **B6. Lista comenzilor in `/admin`** — azi comenzile vin pe email si stau in baza (tabelele `orders`, `order_items`); o lista in admin e un proiect separat.
- **B7. Test pe telefoane reale** (Safari pe iPhone, Chrome pe Android) si in Firefox — testele automate au rulat in Chromium.

## C. Configurarea serverului — `public_html/admin/config.local.php`

Fisierul nu e in git; se editeaza pe server (cPanel → File Manager). Se **adauga** (valorile reale in locul celor intre `<>`):

```php
define('APP_ENV', 'production');              // fara asta, adresele @mugurel-bricolaj.ro raman BLOCATE (garda de test)
define('ORDER_NOTIFY_TO', 'lucian@capitalboost.ro'); // LA INCEPUT: doar pentru comanda de proba (pasul E4)
define('MAIL_FROM', 'comenzi@mugurel-bricolaj.ro');
define('MAIL_FROM_NAME', 'Mugurel Materiale Constructii');
define('SMTP_HOST', '<mail.mugurel-bricolaj.ro>');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');                 // 465 = ssl; 587 = tls
define('SMTP_USER', 'comenzi@mugurel-bricolaj.ro');
define('SMTP_PASS', '<parola casutei>');
```

**NU** se adauga `ORDER_RATE_MAX` si `ORDER_MIN_FILL_SEC` — valorile implicite (10 trimiteri / 10 minute / IP; 3 secunde)
sunt cele de productie; cele din `config.local.php`-ul local (1000 si 0) sunt doar pentru testele automate.

## D. Lansarea, pas cu pas

Durata estimata: 45–60 de minute. Ideal intr-o zi lucratoare dimineata (daca apare o comanda reala, poate fi sunata).

| Pas | Actiune | Verificare |
|---|---|---|
| D1 | **Backup baza de productie**: cPanel → phpMyAdmin → Export (toata baza) → salvat local cu data | fisierul .sql exista si nu e gol |
| D2 | Noteaza commit-ul curent de pe server (cPanel → Git Version Control → „HEAD Commit") | — pentru revenire |
| D3 | Pe PC: `.html` statice incarca `cos.js?v=20261013` — **schimba versiunea** cu data lansarii in cele 7 fisiere `.html` (cauta `cos.js?v=`) si fa commit | `grep -c "cos.js?v=<data>" *.html finantari-europene/index.html` = 7 |
| D4 | (Optional, B1) linia `rm -rf $DEPLOYPATH/admin/tests` in `.cpanel.yml`, commit | — |
| D5 | `git push origin feat/cos-comenzi` | branch-ul apare pe GitHub |
| D6 | Pe server: editeaza `config.local.php` ca la sectiunea C (cu `ORDER_NOTIFY_TO` = `lucian@capitalboost.ro`) | — |
| D7 | cPanel → Git Version Control → Manage → selecteaza **`feat/cos-comenzi`** → Update from Remote → **Deploy HEAD Commit** | — |
| D8 | Citeste log-ul: `https://mugurel-bricolaj.ro/__deploy_m7x2verif.log` | `OK — migrare aplicata` pentru **007, 008, 009, 010**; `Import date: sarit`; `Produse: 534`; niciun `EROARE` |
| D9 | **Cloudflare → Caching → Purge Everything** (js/css noi), apoi Ctrl+Shift+R | `curl -s https://mugurel-bricolaj.ro/js/cos.js?v=x | head -3` arata fisierul nou |

## E. Preturile reale si proba

| Pas | Actiune | Verificare |
|---|---|---|
| E1 | Excel-ul confirmat de Mugurel (A1) urcat pe server langa clona git (cPanel → File Manager) | — |
| E2 | Terminal cPanel, in directorul clonei git: <br>`cp ~/public_html/admin/config.local.php admin/config.local.php` <br>`php db/import_preturi.php "Preturi.xlsx"` (doar verificare) | „534 randuri citite… 0 „de la"", **0 erori**, „S-ar actualiza: N" |
| E3 | Acelasi director: `php db/import_preturi.php "Preturi.xlsx" --apply`, apoi **`rm admin/config.local.php`** (sa nu ramana parola in clona) | „Actualizate: N"; o pagina de categorie arata preturile noi |
| E4 | **Comanda de proba** pe site, cu adresa de email a lui Lucian ca client, un produs ieftin, plata „Ridicare din magazin" | pagina de confirmare; **2 emailuri** la `lucian@capitalboost.ro` (intern + client), nu in Spam; antet `spf=pass`, `dkim=pass` |
| E5 | Comuta `ORDER_NOTIFY_TO` pe `comenzi@mugurel-bricolaj.ro` in `config.local.php` | — |
| E6 | Inca o comanda de proba, anuntata dinainte lui Mugurel | emailul intern ajunge la `comenzi@` |
| E7 | Sterge comenzile de proba (phpMyAdmin): `DELETE FROM orders WHERE email = '<adresa de proba>';` (produsele lor se sterg automat) | `SELECT COUNT(*) FROM orders` = 0 |

## F. Verificari dupa lansare

```bash
B=https://mugurel-bricolaj.ro
# site-ul existent: neschimbat
for p in acoperis izolatie gips-carton zidarie-bca gard-imprejmuiri electrice incalzire sanitare apa-canal gradina mobilier electrocasnice scule-unelte; do
  printf "%-18s %s carduri=%s cos=%s\n" $p "$(curl -s -o /dev/null -w '%{http_code}' $B/$p.html)" \
    "$(curl -s $B/$p.html | grep -o 'class="material-card"\|class="accessory-card' | wc -l)" \
    "$(curl -s $B/$p.html | grep -o 'class="btn-cart"' | wc -l)"
done                                             # 200, 495 carduri in total, butoane = carduri minus indisponibile
curl -s $B/catalog.html | grep -o 'din [0-9]* produse'          # din 534 produse
# paginile noi
curl -s -o /dev/null -w '%{http_code}\n' $B/cos.html             # 200
curl -s -o /dev/null -w '%{http_code}\n' $B/comanda.html         # 200
curl -s -o /dev/null -w '%{http_code}\n' $B/cos-produse.php      # 405 (doar POST)
curl -s -o /dev/null -w '%{http_code}\n' "$B/comanda-trimisa.html?t=000"   # 404
curl -s $B/cos.html | grep -o '<meta name="robots"[^>]*>'        # noindex, nofollow
curl -sI $B/comanda.html | grep -i 'cache-control\|set-cookie'   # no-store; cookie HttpOnly; Secure; SameSite=Lax
# fisiere care NU trebuie sa fie accesibile
curl -s -o /dev/null -w '%{http_code}\n' $B/admin/tests/test_orders.php     # 403 sau 404
curl -s -o /dev/null -w '%{http_code}\n' $B/includes/lib/PHPMailer/PHPMailer.php  # 200 cu pagina GOALA e acceptabil (doar clase); ideal 403
```

Plus, in browser: un produs in cos → cos → formular (PF si PJ) → confirmare, pe telefon si pe calculator.

**Prima saptamana:** zilnic, in phpMyAdmin, comenzile la care emailul n-a plecat:
`SELECT order_number, created_at FROM orders WHERE email_admin_at IS NULL OR email_client_at IS NULL;`
si log-ul de erori PHP din cPanel (cauta `orderNotify`, `mailSend`, `orderCreate`).

## G. Revenire (rollback)

1. cPanel → Git Version Control → selecteaza **`feat/catalog-db`** → Deploy HEAD Commit (~30 s). Paginile revin la varianta
   fara cos (fisierele `.php` de categorie sunt rescrise).
2. Fisierele noi raman in `public_html` (deploy-ul copiaza, nu sterge): `cos.php`, `comanda.php`, `comanda-trimisa.php`,
   `cos-produse.php`, `js/cos*.js`, `js/comanda*.js`, `includes/cart.php`, `includes/order*.php`, `includes/mailer.php`,
   `includes/lib/`. Fara legaturi spre ele nu deranjeaza; pentru curatenie se sterg din File Manager.
3. Tabelele si coloanele noi (`price_is_from`, `stock_status`, `order_attempts`, `orders`, `order_items`) **raman** — codul vechi nu le
   foloseste. **Nu se sterg**: contin comenzile primite.
4. Purge Everything in Cloudflare.
5. Daca problema e la baza de date: restaurare din backup-ul de la D1 (**atentie**: se pierd comenzile primite intre timp —
   exporta-le intai: `SELECT * FROM orders` / `order_items`).

## H. Dupa lansare

- Merge `feat/cos-comenzi` in `main` (jurnalul cere de mult ca productia sa ruleze de pe `main`).
- Sterge `__deploy_m7x2verif.log` daca nu mai e nevoie (jurnal, punctul 2).
- Reverifica lista de IP-uri Cloudflare din `includes/order_security.php` (`CLOUDFLARE_RANGES`) fata de https://www.cloudflare.com/ips/ — se schimba rar.
- Actualizeaza `docs/JURNAL-LUCRU.md` cu data lansarii si ce s-a constatat.
