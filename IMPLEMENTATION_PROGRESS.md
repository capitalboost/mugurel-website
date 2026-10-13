# IMPLEMENTATION_PROGRESS — Cos si comenzi online

Proiect: stoc, cos, comanda (PF/PJ), inregistrare comenzi, emailuri, pagina de confirmare.
Regula: **un task → testat → verificat → corectat → abia apoi urmatorul.**
Nimic pe live, nimic pe baza de productie, niciun email catre `comenzi@mugurel-bricolaj.ro`.

**Status plan:** APROBAT (2026-10-13) — in lucru
**Branch de lucru:** `feat/cos-comenzi`, pornit din `feat/catalog-db` (ramura de pe care ruleaza productia).

---

## Ce am gasit la analiza (inainte de plan)

| Constatare | Consecinta |
|---|---|
| Pe acest PC **nu exista PHP si nici MySQL** (nici Laragon) | Task 1 trebuie sa instaleze mediul local, altfel nimic nu se poate testa |
| Productia ruleaza **PHP 8.1** pe LiteSpeed; Laragon vine cu 8.3 | Fiecare task ruleaza si `php -l` cu reguli 8.1 (fara sintaxa 8.2+) |
| `products` are deja `price` + `price_unit`; `priceLabel()` afiseaza „Pret la cerere" cand lipsesc | Pretul se poate afisa fara schema noua; „de la" cere o coloana noua |
| `Preturi produse - estimari piata.xlsx`: 534/534 cu pret, **toate „DA" (de la)**, toate marcate „estimare euristica, NU oferta verificata" | Bune ca **date de test local**. NU merg pe live pana nu sunt confirmate de Mugurel |
| Nu exista stoc in baza | Task 2 adauga campul |
| Cardurile se randeaza in `includes/product_grid.php` (3 tipuri) + `catalog.php` | Butonul de cos se pune intr-un singur loc per tip de card |
| `admin/helpers/Csrf.php` exista (pattern refolosibil); nu exista niciun cod de email | Emailul se construieste de la zero (PHPMailer, inclus in repo) |
| Limitarea la login foloseste `REMOTE_ADDR` = IP Cloudflare | Rate-limit-ul pe comenzi trebuie sa citeasca `CF-Connecting-IP` corect (Task 9) |
| Paginile `.html` (index, contact) sunt statice si cache-uite | Cosul se tine in browser (localStorage), contorul din header merge si pe `.html` |
| `.cpanel.yml` copiaza explicit directoarele | Orice director nou (ex. `api/`) trebuie adaugat acolo — notat pentru Task 15 |

## Decizii de arhitectura propuse

1. **Cosul traieste in browser** (`localStorage`), nu in sesiune PHP: merge pe paginile `.html` statice,
   nu strica cache-ul Cloudflare, nu cere cookie de sesiune pentru vizitatori.
2. **Serverul nu are incredere in browser:** la pagina de cos si la trimiterea comenzii, pretul,
   numele si stocul se recitesc din baza dupa `id`. Ce e in localStorage e doar lista de id + cantitate.
3. **Comanda = snapshot:** `order_items` copiaza numele, pretul, unitatea si „de la" din momentul
   comenzii, ca o schimbare ulterioara de pret sa nu modifice comenzi vechi.
4. **Emailuri prin SMTP cu PHPMailer** (fisiere incluse in repo, fara Composer). Local, SMTP-ul e
   **Mailpit** (`127.0.0.1:1025`) — prinde *toate* mesajele, nimic nu iese din PC.
5. **Destinatarii sunt in config, nu in cod:** `ORDER_NOTIFY_TO` in `admin/config.local.php`
   (local = `lucian@capitalboost.ro`). Cod de garda: in mediul local, trimiterea catre
   `@mugurel-bricolaj.ro` e refuzata.
6. Pagini noi: `cos.php`, `comanda.php`, `comanda-trimisa.php`, endpoint `api/cos.php`.

---

## Taskuri

Complexitate: **S** = mic (≤ ~2h), **M** = mediu, **L** = mare (impartit in subtaskuri).

### T1 — Analiza si mediul local · M · **Finalizat**
- **Obiectiv:** mediu local identic functional cu productia, cu toate testele existente verzi.
- **De facut:** instalare Laragon (PHP + MySQL/MariaDB), Mailpit, Playwright (Chromium);
  refacere baza locala din `db/` (`schema.sql`, migrari, `productie_date.sql`, `deploy_db.php`);
  vhost local; branch `feat/cos-comenzi`.
- **Acceptanta:** cele 5 teste din `admin/tests/` trec; cele 13 pagini dau 200 local cu
  231 materiale + 264 accesorii; catalogul arata „din 534 produse"; `/admin` permite login local;
  un email de proba apare in Mailpit.
- **Necesita aprobare:** instalarea de software pe PC (winget/npx).

### T2 — Preturi si „de la" (prerechizit pentru cos) · M · Neinceput
- **Obiectiv:** produsele au pret afisat corect, inclusiv „de la X lei".
- **De facut:** `db/migration_007_price_from.sql` (`price_is_from TINYINT`); script CLI
  `db/import_preturi.php` care citeste Excel-ul (exportat CSV) dupa `ID` → **doar in baza locala**;
  `priceLabel()` cu „de la"; bifa „de la" in formularul din admin.
- **Teste:** import pe copie locala (534 actualizate, 0 erori, ID inexistent raportat, rulare repetata
  idempotenta); `test_product_grid.php` extins pentru cele 3 variante de afisare; adminul salveaza bifa.
- **Acceptanta:** pe pagini locale apare „de la 55,00 lei / m²"; produsele fara pret raman „Pret la cerere".

### T3 — Indicator stoc · S/M · Neinceput
- **De facut:** `migration_008_stock.sql` cu `stock_status ENUM('in_stoc','la_comanda','indisponibil')`;
  select in admin; eticheta pe cele 3 tipuri de card si in catalog.
- **Teste:** randare pentru fiecare stare; adminul salveaza starea; „indisponibil" nu primeste buton de cos (verificat in T4).

### T4 — Butonul „Adauga in cos" · M · Neinceput
- **De facut:** buton pe toate cardurile (`product_grid.php`, `catalog.php`) cu `data-id`;
  `js/cos.js` (fisier nou, fara inline → CSP neschimbat); feedback vizual la adaugare.
- **Teste:** buton prezent pe toate cardurile cu pret si stoc ≠ indisponibil (numarat pe cele 13
  pagini + catalog); absent la indisponibil; click → evenimentul ajunge in JS (Playwright).

### T5 — Pastrarea produselor in cos · S/M · Neinceput
- **De facut:** `localStorage` (doar `{id, qty}`), contor in header pe paginile PHP **si** `.html`;
  cantitatea creste daca produsul e deja in cos; toleranta la localStorage blocat/corupt.
- **Teste (Playwright):** adaug pe o pagina, contorul se vede pe alta pagina si dupa reload;
  date corupte in localStorage nu strica pagina.

### T6 — Pagina de cos si cantitati · L · Neinceput
- **T6a** `api/cos.php`: primeste id-uri, intoarce nume/pret/unitate/stoc **din baza**; id-urile
  invalide/ascunse sunt eliminate. Teste PHP pe endpoint.
- **T6b** `cos.php`: lista, +/−, stergere, subtotal pe rand, total; mesaj „total estimativ" cand exista
  produse „de la"; cos gol. Teste Playwright + verificare ca pretul modificat in localStorage e ignorat.

### T7 — Formular persoana fizica · M · Neinceput
- **De facut:** `comanda.php`: nume, telefon, email, judet, localitate, adresa, mentiuni;
  validare in browser (HTML5) — validarea serioasa e pe server in T10.
- **Teste:** campuri obligatorii, formate (telefon RO, email), afisarea erorilor, pastrarea datelor la eroare.

### T8 — Formular persoana juridica · M · Neinceput
- **De facut:** comutator PF/PJ; denumire firma, CUI (cu verificarea cifrei de control), Nr. Reg. Com.,
  sediu social, persoana de contact.
- **Teste:** CUI valid/invalid (cu si fara „RO"), comutarea nu pierde datele comune.

### T9 — Plata si mesaje despre transport · S · Neinceput
- **De facut:** metodele de plata alese (vezi „Decizii"), texte despre transport pe cos si pe formular.
- **Teste:** metoda e obligatorie; textele apar pe mobil si desktop.

### T10 — Validari si securitate (server) · M · Neinceput
- **De facut:** validare completa pe server (aceleasi reguli ca in browser, plus lungimi maxime),
  CSRF, honeypot anti-bot, rate-limit pe IP (cu `CF-Connecting-IP` acceptat **doar** din range-urile
  Cloudflare), protectie la header injection pentru email, bifa GDPR + link politica de confidentialitate.
- **Teste:** cereri POST manuale: fara CSRF → respins; honeypot completat → respins; prea multe
  cereri → blocat; CUI/telefon/email invalide → erori; caractere speciale/HTML → escapate.

### T11 — Inregistrarea comenzilor si anti-duplicat · M · Neinceput
- **De facut:** `migration_009_orders.sql` (`orders`, `order_items`); numar comanda `MUG-2026-000001`;
  tranzactie; token unic per formular (UNIQUE in baza) + redirect dupa POST.
- **Teste:** comanda corecta salvata cu snapshot; dublu-click / refresh / retrimitere → **o singura** comanda;
  esec la jumatate → nimic salvat (rollback).

### T12 — Notificare email catre `lucian@capitalboost.ro` · M · Neinceput
- **De facut:** PHPMailer in `includes/lib/`; `ORDER_NOTIFY_TO` din config; email cu toate datele comenzii;
  garda care refuza `@mugurel-bricolaj.ro` local; esecul emailului **nu** pierde comanda (e deja salvata).
- **Teste:** emailul apare in Mailpit cu destinatar `lucian@capitalboost.ro` si continut complet;
  SMTP oprit → comanda ramane salvata si eroarea e logata.
- **Optional:** livrare reala catre `lucian@capitalboost.ro` — doar daca primesc date SMTP de test.

### T13 — Email de confirmare catre client (sandbox) · S/M · Neinceput
- **De facut:** sablon de confirmare (rezumat, total, pasii urmatori, contact).
- **Teste:** mesajul catre adresa clientului apare **doar in Mailpit**; nimic nu iese din PC.

### T14 — Pagina de confirmare · S · Neinceput
- **De facut:** `comanda-trimisa.php` accesibila doar cu token aleator (nu cu id incremental → nu se pot
  ghici comenzile altora); goleste cosul.
- **Teste:** token valid → rezumat; token gresit → 404; cosul e gol dupa confirmare.

### T15 — Testare integrata si responsive · M · Neinceput
- **De facut:** flux complet PF si PJ cu Playwright la 375 / 768 / 1280 px, cu capturi de ecran;
  regresie: 13 pagini 200, 231 + 264 carduri, catalog 534, JSON-LD, admin; toate testele PHP.
- **Acceptanta:** totul verde, capturi atasate in acest fisier.

### T16 — Plan de lansare in productie (fara deploy) · S · Neinceput
- Document: ordinea migrarilor, modificari `.cpanel.yml`, date SMTP de productie, comutarea
  `ORDER_NOTIFY_TO` pe `comenzi@mugurel-bricolaj.ro`, **confirmarea preturilor reale**, purge Cloudflare,
  revenire (rollback), verificari dupa lansare, pagini legale (termeni, retur, ANPC/SOL).
- **Nu se executa nimic in productie fara aprobarea ta explicita.**

> Fata de lista ta am adaugat **T2 (preturi)** — cosul nu are sens fara preturi — si am numerotat
> restul cu +1. Ordinea „mecanism de pastrare" / „buton" e pastrata, dar T4 si T5 se testeaza impreuna
> in browser, fiindca butonul fara stocare nu are efect vizibil.

---

## Riscuri si lucruri de stiut

- **Preturile sunt estimari.** Fisierul spune explicit „NU este oferta verificata". Le folosesc doar local.
  Pe live merg doar dupa ce Mugurel le confirma — blocant pentru T16.
- **Decizia din 13 oct „doar afisare pret" e inlocuita** de acest proiect (cos + comanda). Plata ramane
  offline (fara procesator de carduri), daca nu decizi altfel.
- **Nu exista lista de comenzi in `/admin`.** Comenzile vin pe email si stau in baza. O lista in admin
  ar fi un task separat, dupa acest proiect.
- **Vanzarea online catre persoane fizice** are cerinte legale (informare pret total, drept de retragere,
  ANPC/SOL). Le listez in T16 ca verificari; nu inlocuiesc un avocat.

## Decizii (2026-10-13)

- [x] **Plata:** ramburs la livrare, transfer bancar (OP, pe baza de proforma), plata la ridicare din magazin.
      Fara card online.
- [x] **Preturi „de la":** in cos intra **doar produsele cu pret fix**. Cele „de la" au doar „Cere oferta" (WhatsApp).
      Consecinta: cu Excel-ul actual (534/534 „de la") **niciun produs nu poate fi pus in cos** pana nu se
      confirma preturi fixe. Pentru teste, baza **locala** primeste cateva produse cu pret fix (fixturi de test).
- [x] **Stoc initial:** toate cele 534 de produse „In stoc" (default al coloanei).
- [x] **Plan aprobat**, inclusiv instalarea mediului local. Continui automat task cu task.

---

## Jurnal pe taskuri

_(se completeaza dupa fiecare task: fisiere modificate, functionalitati, teste rulate, rezultate,
probleme gasite si rezolvate, status)_

### Cum pornesti si testezi local

```bash
bash dev/start_local.sh     # MariaDB :3306, Mailpit :1025/:8025, PHP 8.1 :8080
bash dev/run_tests.sh       # teste PHP + php -l (8.1) + regresie pe cele 13 pagini si catalog
bash dev/e2e/run.sh         # teste in browser la 375 / 768 / 1280 px
```
Admin local: `http://localhost:8080/admin/` — user `admin`, parola `local-test-2026` (doar in baza locala).
Emailuri locale: `http://localhost:8025`.

---

### T1 — Analiza si mediul local — Finalizat (2026-10-13)

**Obiectiv:** mediu local echivalent cu productia, cu toate verificarile existente verzi.

**Mediu instalat** (in afara repo-ului, nimic pe server):
- PHP **8.1.34** NTS (winget `PHP.PHP.NTS.8.1`) — **aceeasi versiune ca productia** (mai bine decat
  Laragon, care vine cu 8.3). `php.ini` cu pdo_mysql, mbstring, openssl, intl, curl, gd, zip; fus orar Bucuresti.
- MariaDB **10.11.10** portabil (checksum SHA-256 verificat) in `%LOCALAPPDATA%\mugurel-dev`, doar pe 127.0.0.1.
- Mailpit **1.31.5** — SMTP `127.0.0.1:1025`, interfata `:8025`. Prinde toate mesajele.
- Playwright **1.49.1** + Chromium, instalat in `%LOCALAPPDATA%\mugurel-dev\e2e` (vezi „Probleme").

**Fisiere noi/modificate:**
- `dev/router_local.php` — routerul serverului PHP incorporat: reproduce `.htaccess` (rewrite `.html`→`.php`,
  `router.php`, blocarea `admin/helpers` etc.) si trimite **aceleasi header-e de securitate (CSP)**, citite
  din `.htaccess`. `dev/` nu e copiat de `.cpanel.yml` → nu ajunge pe server.
- `dev/start_local.sh`, `dev/run_tests.sh` (runner unic: teste PHP + `php -l` + regresie pagini),
  `dev/e2e/` (config Playwright, `run.sh`, `tests/baseline.spec.js`).
- `.gitignore` — artefactele Playwright.

**Baza locala:** refacuta din repo exact ca la deploy (`schema.sql` → user id=1 → `db/deploy_db.php`):
5 migrari aplicate, **534 produse, 464 proprietati, 31 categorii** — identic cu productia.
Userul admin local are o parola noua de test (nu cea din `db/seed_admin.php`).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `admin/tests/*` (5 scripturi) | **198/198** asertiuni trecute (= cifra din jurnal) |
| `php -l` cu PHP 8.1 pe toate fisierele PHP | trecut |
| 13 pagini de categorie local | toate 200, **231 materiale + 264 accesorii**, JSON-LD pe fiecare |
| Catalog | „din 534 produse" |
| Pagini statice `/`, `index`, `contact`, `catalog` | 200; `/nu-exista.html` → 404 |
| `admin/helpers/Csrf.php` | 403 (protectia din `admin/.htaccess` reprodusa) |
| Login admin local (curl, cu CSRF) | 302 → lista de produse, 534 randuri |
| Email de proba catre `lucian@capitalboost.ro` | prins in Mailpit, nimic trimis in afara |
| Playwright: 5 pagini + catalog × 3 latimi | **18/18** trecute |

**Probleme gasite si rezolvate:**
1. `npm install` in folderul Google Drive dura >10 minute (Drive sincroniza fiecare fisier din `node_modules`).
   Rezolvat: `node_modules` stau in `%LOCALAPPDATA%\mugurel-dev\e2e`, iar `dev/e2e/run.sh` copiaza testele acolo.
   Raman 3 fisiere `LICENSE` blocate de Drive in `dev/e2e/node_modules/` (ignorate de git) — se sterg cand le elibereaza Drive.
2. Primul test in browser pica pe toate paginile: zgomot din scripturile terte blocate intentionat in test.
   Testul verifica acum raspunsurile HTTP dupa URL, nu mesajele generice.

**Probleme existente si pe live (nu introduse de noi), lasate vizibile in test ca „cunoscute":**
- fotografii de produs lipsa (404) — jurnal, punctul 7;
- **nou:** harta Google de pe `contact.html` e blocata de CSP (lipseste `frame-src`) — trecut in jurnal ca punctul 11.
