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

### T2 — Preturi si „de la" (prerechizit pentru cos) · M · **Finalizat**
- **Obiectiv:** produsele au pret afisat corect, inclusiv „de la X lei".
- **De facut:** `db/migration_007_price_from.sql` (`price_is_from TINYINT`); script CLI
  `db/import_preturi.php` care citeste Excel-ul (exportat CSV) dupa `ID` → **doar in baza locala**;
  `priceLabel()` cu „de la"; bifa „de la" in formularul din admin.
- **Teste:** import pe copie locala (534 actualizate, 0 erori, ID inexistent raportat, rulare repetata
  idempotenta); `test_product_grid.php` extins pentru cele 3 variante de afisare; adminul salveaza bifa.
- **Acceptanta:** pe pagini locale apare „de la 55,00 lei / m²"; produsele fara pret raman „Pret la cerere".

### T3 — Indicator stoc · S/M · **Finalizat**
- **De facut:** `migration_008_stock.sql` cu `stock_status ENUM('in_stoc','la_comanda','indisponibil')`;
  select in admin; eticheta pe cele 3 tipuri de card si in catalog.
- **Teste:** randare pentru fiecare stare; adminul salveaza starea; „indisponibil" nu primeste buton de cos (verificat in T4).

### T4 — Butonul „Adauga in cos" · M · **Finalizat**
- **De facut:** buton pe toate cardurile (`product_grid.php`, `catalog.php`) cu `data-id`;
  `js/cos.js` (fisier nou, fara inline → CSP neschimbat); feedback vizual la adaugare.
- **Teste:** buton prezent pe toate cardurile cu pret si stoc ≠ indisponibil (numarat pe cele 13
  pagini + catalog); absent la indisponibil; click → evenimentul ajunge in JS (Playwright).

### T5 — Pastrarea produselor in cos · S/M · **Finalizat**
- **De facut:** `localStorage` (doar `{id, qty}`), contor in header pe paginile PHP **si** `.html`;
  cantitatea creste daca produsul e deja in cos; toleranta la localStorage blocat/corupt.
- **Teste (Playwright):** adaug pe o pagina, contorul se vede pe alta pagina si dupa reload;
  date corupte in localStorage nu strica pagina.

### T6 — Pagina de cos si cantitati · L · **Finalizat**
- **T6a** `api/cos.php`: primeste id-uri, intoarce nume/pret/unitate/stoc **din baza**; id-urile
  invalide/ascunse sunt eliminate. Teste PHP pe endpoint.
- **T6b** `cos.php`: lista, +/−, stergere, subtotal pe rand, total; mesaj „total estimativ" cand exista
  produse „de la"; cos gol. Teste Playwright + verificare ca pretul modificat in localStorage e ignorat.

### T7 — Formular persoana fizica · M · **Finalizat**
- **De facut:** `comanda.php`: nume, telefon, email, judet, localitate, adresa, mentiuni;
  validare in browser (HTML5) — validarea serioasa e pe server in T10.
- **Teste:** campuri obligatorii, formate (telefon RO, email), afisarea erorilor, pastrarea datelor la eroare.

### T8 — Formular persoana juridica · M · **Finalizat**
- **De facut:** comutator PF/PJ; denumire firma, CUI (cu verificarea cifrei de control), Nr. Reg. Com.,
  sediu social, persoana de contact.
- **Teste:** CUI valid/invalid (cu si fara „RO"), comutarea nu pierde datele comune.

### T9 — Plata si mesaje despre transport · S · **Finalizat**
- **De facut:** metodele de plata alese (vezi „Decizii"), texte despre transport pe cos si pe formular.
- **Teste:** metoda e obligatorie; textele apar pe mobil si desktop.

### T10 — Validari si securitate (server) · M · **Finalizat**
- **De facut:** validare completa pe server (aceleasi reguli ca in browser, plus lungimi maxime),
  CSRF, honeypot anti-bot, rate-limit pe IP (cu `CF-Connecting-IP` acceptat **doar** din range-urile
  Cloudflare), protectie la header injection pentru email, bifa GDPR + link politica de confidentialitate.
- **Teste:** cereri POST manuale: fara CSRF → respins; honeypot completat → respins; prea multe
  cereri → blocat; CUI/telefon/email invalide → erori; caractere speciale/HTML → escapate.

### T11 — Inregistrarea comenzilor si anti-duplicat · M · **Finalizat**
- **De facut:** `migration_009_orders.sql` (`orders`, `order_items`); numar comanda `MUG-2026-000001`;
  tranzactie; token unic per formular (UNIQUE in baza) + redirect dupa POST.
- **Teste:** comanda corecta salvata cu snapshot; dublu-click / refresh / retrimitere → **o singura** comanda;
  esec la jumatate → nimic salvat (rollback).

### T12 — Notificare email catre `lucian@capitalboost.ro` · M · **Finalizat**
- **De facut:** PHPMailer in `includes/lib/`; `ORDER_NOTIFY_TO` din config; email cu toate datele comenzii;
  garda care refuza `@mugurel-bricolaj.ro` local; esecul emailului **nu** pierde comanda (e deja salvata).
- **Teste:** emailul apare in Mailpit cu destinatar `lucian@capitalboost.ro` si continut complet;
  SMTP oprit → comanda ramane salvata si eroarea e logata.
- **Optional:** livrare reala catre `lucian@capitalboost.ro` — doar daca primesc date SMTP de test.

### T13 — Email de confirmare catre client (sandbox) · S/M · **Finalizat**
- **De facut:** sablon de confirmare (rezumat, total, pasii urmatori, contact).
- **Teste:** mesajul catre adresa clientului apare **doar in Mailpit**; nimic nu iese din PC.

### T14 — Pagina de confirmare · S · **Finalizat**
- **De facut:** `comanda-trimisa.php` accesibila doar cu token aleator (nu cu id incremental → nu se pot
  ghici comenzile altora); goleste cosul.
- **Teste:** token valid → rezumat; token gresit → 404; cosul e gol dupa confirmare.

### T15 — Testare integrata si responsive · M · **Finalizat**
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
- [x] **MODIFICAT 13 oct (dupa T8): toate produsele au pret FIX** si toate cele disponibile se pot pune in cos.
      „De la" nu mai exista nicaieri: nici pe site, nici in admin, nici la import. Decizia de mai jos e inlocuita.
- [x] ~~**Preturi „de la":** in cos intra **doar produsele cu pret fix**.~~ Cele „de la" au doar „Cere oferta" (WhatsApp).
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

---

### T2 — Preturi si „de la" — Finalizat (2026-10-13)

**Obiectiv:** produsele afiseaza pretul cu TVA, inclusiv varianta „de la X lei"; preturile se pot
importa din Excel si bifa „de la" se poate seta din admin.

**Fisiere:**
- `db/migration_007_price_from.sql` (nou) — `products.price_is_from TINYINT(1) DEFAULT 0`, idempotenta.
- `db/deploy_db.php` — include migrarea 007 si **reaplica migrarile dupa un import reusit** (vezi „Probleme").
- `db/import_preturi.php` (nou) — citeste direct `.xlsx` (foaia „Preturi", coloane recunoscute dupa antet),
  valideaza tot inainte sa scrie, totul intr-o tranzactie; implicit doar verificare, scrie doar cu `--apply`.
- `includes/product_grid.php` — `priceLabel()` cu „de la"; **pretul apare acum si pe cardurile de accesorii**.
- `includes/products_repo.php`, `router.php` — citesc `price_is_from`.
- `templates/category.php` (paginile de subcategorie servite de `router.php`) — acelasi format de pret ca restul site-ului
  (inainte: „55.00 RON/m²", acum „55,00 lei / m²").
- `css/style.css` — `.accessory-price` (acelasi stil ca `.material-price`).
- `admin/views/products/edit.php`, `admin/controllers/ProductsController.php`, `admin/models/Product.php` — bifa „Pret minim".
- Teste: `admin/tests/test_product_grid.php` (+14), `admin/tests/test_import_preturi.php` (nou, 25),
  `dev/e2e/tests/admin-pret.spec.js` (nou), `dev/test_fresh_deploy.sh` (nou).

**Date locale:** importate estimarile din `Preturi produse - estimari piata.xlsx` → 534/534 cu pret, toate „de la".
**Doar in baza locala.** Productia nu e atinsa.

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `test_product_grid.php` — 9 variante `priceLabel()` (null, 0, fix, „de la", fara unitate, escapare, separatori RO) + afisare pe cele 3 tipuri de card | 130/130 |
| `test_import_preturi.php` — citire xlsx (shared + inline strings), 7 tipuri de randuri invalide, verificare fara scriere, idempotenta, rollback la ID inexistent, restaurarea bazei | 25/25 |
| Import real, verificare: estimari → 534 de schimbat; sablonul gol → 0 de schimbat | trecut |
| Import real `--apply` de doua ori | 534 actualizate, apoi 0 (idempotent) |
| `dev/test_fresh_deploy.sh` — deploy pe baza goala, de 2 ori | coloana exista dupa **fiecare** rulare; 534 produse |
| **Control negativ:** acelasi test cu reaplicarea migrarilor dezactivata | **pica** la rularea 1 (cum trebuie) |
| Pagini: carduri cu „de la" | 231/231 materiale, 264/264 accesorii, catalog si subcategorii OK |
| Playwright admin: debifez → pe site „55,00 lei / m²"; bifez → „de la 55,00 lei / m²"; restaurat | trecut |
| Produsul 1 dupa salvarea din admin — celelalte campuri (wa_text, badge, imagine, sectiune) | neschimbate |
| Regresie completa `dev/run_tests.sh` | **237** asertiuni, `php -l` 8.1, 231+264, catalog 534 — trecut |
| Regresie browser `dev/e2e/run.sh` | 19 trecute, 2 sarite (adminul doar pe desktop) |
| Captura vizuala accesorii, mobil 375 px + desktop | pretul aliniat, acelasi stil ca la materiale |

**Probleme gasite si rezolvate:**
1. **Bug latent la deploy pe baza goala:** `deploy_db.php` rula migrarile *inainte* de import, iar `productie_date.sql`
   face `DROP TABLE products` → orice coloana adaugata de o migrare noua disparea la primul deploy pe un server nou.
   Pe productia actuala nu se manifesta (importul e sarit), dar ar fi lovit la T3 si T11. Reparat si acoperit de test.
2. Cititorul de `.xlsx`: atributele XML nu se citeau dupa `children($ns)` → corectat cu `attributes()`.
3. Produsul 78 are `price_unit = ''` in loc de `NULL` → importul raporta o modificare falsa; acum `''` = `NULL`.
4. Testul de admin: sintaxa `test.skip` cu callback nu primeste `testInfo` in Playwright 1.49 → mutat in `beforeEach`.

**De retinut pentru T16 (lansare):**
- Textele meta din `catalog.html` (og/twitter description) spun „Pret la cerere pe WhatsApp" — de actualizat cand apar preturile reale.
- Estimarile NU merg pe live. Importul pe productie se face cu fisierul confirmat de Mugurel.

---

### T3 — Indicator stoc — Finalizat (2026-10-13)

**Obiectiv:** fiecare produs arata starea stocului; Mugurel o seteaza din admin.

**Fisiere:**
- `db/migration_008_stock.sql` (nou) — `products.stock_status ENUM('in_stoc','la_comanda','indisponibil') DEFAULT 'in_stoc'`
  (decizia: toate produsele pornesc „In stoc"). DDL scris cu ghilimele simple, ca sa mearga si cu `ANSI_QUOTES`.
- `db/deploy_db.php` — migrarea 008 in lista.
- `includes/product_grid.php` — `STOCK_LABELS` + `stockLabel()`; eticheta pe cardurile de material, catalog si accesoriu.
  O stare lipsa sau necunoscuta nu afiseaza nimic.
- `templates/category.php` (subcategorii), `includes/products_repo.php`, `router.php` — citesc si afiseaza starea.
- `css/style.css` — `.stock` cu punct colorat: verde / portocaliu inchis / rosu (contrast ≥ 4,5:1 pe alb).
- Admin: `edit.php` (select „Stoc"), `ProductsController.php` (lista fixa de valori acceptate, altfel eroare), `Product.php`.
- Teste: `test_product_grid.php` (+9), `dev/e2e/tests/admin-stoc.spec.js` (nou).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `stockLabel()`: cele 3 stari, stare lipsa, valoare necunoscuta (`<script>`) | trecut — valoarea necunoscuta nu ajunge in HTML |
| Eticheta apare o singura data pe fiecare tip de card | trecut (material, catalog, accesoriu) |
| Pagini locale | **495/495** carduri cu „In stoc" pe cele 13 pagini; catalog 60/60 pe pagina 1; subcategorie 5/5 |
| Playwright admin: in_stoc → la_comanda → indisponibil, verificat pe `acoperis.html`, apoi restaurat | trecut |
| Playwright admin: valoare falsificata in formular (`hack`) | respinsa cu „Starea de stoc nu e valida.", site neschimbat |
| `dev/test_fresh_deploy.sh products.price_is_from products.stock_status` | coloanele exista dupa fiecare rulare |
| Regresie `dev/run_tests.sh` | **246** asertiuni, `php -l` 8.1, 231+264, catalog 534 — trecut |
| Regresie browser | 21 trecute, 6 sarite (adminul doar pe desktop) |
| Captura mobil 375 px | eticheta deasupra pretului, lizibila |

**Probleme:** niciuna in cod. Sesiunea de lucru s-a intrerupt dupa teste; serviciile locale au fost repornite cu
`dev/start_local.sh`, iar regresia completa a fost rulata din nou inainte de commit.

---

### T4 — Butonul „Adauga in cos" — Finalizat (2026-10-13)

**Obiectiv:** produsele care se pot comanda online au buton „Adauga in cos"; restul raman pe WhatsApp.

**Regula (o singura functie, `canAddToCart()`):** pret > 0 **si** nu e „de la" **si** stocul nu e „indisponibil".
Aceeasi regula va fi reverificata pe server in T6/T10 — butonul e doar interfata.

**Fisiere:**
- `includes/product_grid.php` — `canAddToCart()`, `cartButton()`; butonul pe cardurile de material, catalog, accesoriu.
- `templates/category.php` — butonul si pe paginile de subcategorie (`router.php`).
- `js/cos.js` (nou) — click delegat pe `[data-cart-id]`: confirmare pe buton („Adaugat in cos ✓", 1,8 s),
  mesaj `role="status"` (citit si de cititoarele de ecran), eveniment `cos:adauga {id, name}` pentru T5.
- Toate cele **22 de pagini** care incarca `main.js` incarca acum si `cos.js` cu `?v=`: pe paginile PHP din `filemtime`,
  pe cele 7 pagini `.html` cu `?v=20261013` (de schimbat manual cand se modifica `cos.js` — notat pentru T16).
- `css/style.css` — `.btn-cart` (verde inchis de brand, distinct de verdele WhatsApp), `.cart-toast`.
- `dev/fixtures/cos_test.sql` (nou) — **doar local**: 9 produse cu pret fix (unul „la comanda", unul „indisponibil",
  unul cu zecimale 12,49 lei/ml). `dev/e2e/run.sh` il aplica automat inainte de teste.
- Teste: `test_product_grid.php` (+17), `dev/e2e/tests/cos-buton.spec.js` (nou).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `canAddToCart()` — 8 cazuri (fix, la comanda, indisponibil, „de la" ca int si ca text din PDO, fara pret, 0, date lipsa) | trecut |
| `cartButton()` — id, nume escapat in atribut (`"`, `<`), `type="button"`, absent fara id / la „de la" | trecut |
| Butonul apare o singura data pe fiecare tip de card | trecut |
| Pagini locale cu fixturi: acoperis 3 butoane, electrice 3 (indisponibilul fara buton), catalog, subcategorii `bucatarie`, `iluminat` | trecut |
| Playwright × 3 latimi: butoane doar unde trebuie; click → text, clasa, mesaj, evenimentul cu `{id, name}` corect; revenire la textul initial | trecut |
| Playwright: Enter de la tastatura (tableta, desktop) | trecut |
| Playwright: pagina de subcategorie servita de `router.php` | trecut |
| Catalog: click pe „Adauga in cos" **nu** deschide WhatsApp (handlerele din `main.js`), 0 erori JS | trecut |
| Regresie `dev/run_tests.sh` | **263** asertiuni, `php -l` 8.1, 231+264, catalog 534 — trecut |
| Regresie browser completa | **32** trecute, 7 sarite (admin doar desktop, tastatura nu pe mobil) |

**Probleme gasite si rezolvate:**
1. Pe Windows, `glob` a intors `templates\footer.php` → sablonul a primit calea gresita pentru `filemtime` (`?v=` gol).
   Corectat si verificat: subcategoriile primesc `cos.js?v=<timestamp>`.
2. Pe mobil, mesajul de confirmare aparea peste bannerul de cookie-uri. Mutat sus, sub header (vezi captura).

**De stiut:** pe datele actuale (toate „de la") **niciun buton nu apare pe live**, conform deciziei „doar pret fix in cos".

---

### T5 — Pastrarea produselor in cos — Finalizat (2026-10-13)

**Obiectiv:** produsele adaugate raman in cos intre pagini si dupa reincarcare; numarul se vede in header peste tot.

**Fisiere:**
- `js/cos.js` — stocare in `localStorage` (cheia `mugurel_cos`, format `{v:1, items:[{id, qty}]}`):
  - **doar id + cantitate** — numele, pretul si stocul se recitesc din baza pe server (T6);
  - citirea curata orice date neasteptate: JSON corupt, dubluri, id invalid, cantitate ≤ 0; maximum 100 de randuri;
  - cantitati cu pana la 2 zecimale (pentru m², ml), plafonate la 99 999; virgula acceptata;
  - daca browserul nu permite salvarea (mod privat, stocare blocata): mesaj rosu clar cu alternativa (WhatsApp / telefon),
    **fara** confirmare falsa pe buton;
  - legatura „Cos" inserata in `.hdr-right` pe toate paginile (cosul exista doar cu JavaScript, deci si legatura);
    contorul = numarul de produse distincte (120 m² de tabla = 1 produs); `aria-label` cu numarul pentru cititoarele de ecran;
  - sincronizare intre tab-uri (evenimentul `storage`);
  - `window.MugurelCos` (read, add, setQty, remove, clear, count) pentru pagina cosului din T6.
- `css/style.css` — `.hdr-cart` (acelasi stil ca butonul de telefon), pe mobil doar iconita + numar; `.cart-toast--error`.
- Teste: `dev/e2e/tests/cos-stocare.spec.js` (nou, 8 scenarii × 3 latimi).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| Cos gol: legatura spre `/cos.html`, fara numar, `aria-label` „0 produse" | trecut × 3 latimi |
| Adaug pe `acoperis` → `electrice` → `index.html` (static) → subcategorie `router.php` → reload: numarul se pastreaza | trecut |
| Acelasi produs de doua ori → un rand, cantitate 2 | trecut |
| In `localStorage` nu ajung pret sau nume | trecut |
| JSON corupt / dubluri / id-uri si cantitati invalide / „2,5" → curatate, 0 erori JS, adaugarea rescrie cosul curat | trecut |
| `localStorage` blocat (SecurityError simulat) → mesaj de eroare, butonul nu spune „Adaugat" | trecut |
| Doua tab-uri: adaug in A → numarul se actualizeaza in B | trecut |
| Header fara scroll orizontal; pe mobil doar iconita + numar, pe tableta/desktop si textul „Cos" | trecut |
| Capturi header la 375 / 768 / 1280 px | aliniat, se potriveste cu butoanele existente |
| Regresie `dev/run_tests.sh` | 263 asertiuni, `php -l`, 231+264, catalog 534 — trecut |
| Regresie browser completa | **56** trecute, 7 sarite |

**Probleme:** niciuna in cod. Testele au trecut din prima rulare; capturile de ecran au fost refacute pe elementul
header, pentru ca bannerul de cookie-uri si butonul WhatsApp il acopereau in captura completa.

**De stiut:** legatura duce la `/cos.html`, pagina care se construieste in T6 (pana atunci 404 local).

---

### T6 — Pagina de cos si cantitati — Finalizat (2026-10-13)

**Obiectiv:** `/cos.html` arata produsele alese cu pretul **din baza**, permite schimbarea cantitatii si scoaterea produselor.

**T6a — server (`includes/cart.php`, `cos-produse.php`):**
- `cartNormalizeItems()` — curata ce vine din browser (id intreg > 0, cantitate > 0 cu max 2 zecimale, fara dubluri, max 100).
- `cartProducts()` — citeste din baza doar produsele **vizibile**; la eroare de baza intoarce `null`
  (nu `[]`, care ar fi afisat fals „produsul nu mai exista" la toate randurile).
- `priceCart()` — **singurul loc** cu regula de comanda (refolosit la inregistrarea comenzii in T11): fiecare rand e
  comandabil (pret, subtotal) sau are un motiv clar; sumele in **bani (intregi)** — 0,10 × 3 = 0,30 exact;
  la unitatile cu zecimale (m², ml, kg…) se accepta 2,5; la buc/set doar intregi.
- `cos-produse.php` (la radacina — `.cpanel.yml` copiaza deja `*.php`, deploy-ul nu se schimba):
  doar POST, max 20 KB, raspuns JSON `no-store`, 503 cu mesaj si telefon cand baza nu raspunde.

**T6b — pagina (`cos.php`, `js/cos-pagina.js`):**
- `noindex, nofollow`; construita pe `templates/header.php` / `footer.php`.
- Randuri cu imagine, nume, pret/unitate, stoc, cantitate (− / camp / +), subtotal, „Scoate"; sumar cu total cu TVA
  si „Costul transportului nu este inclus" (mesajele de transport complete vin in T9).
- Produsele care nu se mai pot comanda raman vizibile cu motivul; „Continua comanda" e blocat pana sunt scoase.
- Raspunsurile vechi ale serverului sunt ignorate daca a plecat deja o cerere mai noua (clicuri rapide pe +).
- `templates/header.php` (`$metaRobots`) si `templates/footer.php` (`$pageScripts`) — optiuni noi, cu valori implicite
  care nu schimba paginile existente.

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `test_cart.php` (nou): normalizare (14 cazuri), unitati cu zecimale, `formatLei`, calcul (0,10×3; 12,49×2,5=31,23; „de la"; indisponibil; la comanda; fara pret; inexistent; 1,5 buc), chei identice pe randuri, produs ascuns, restaurare | **24/24** |
| `curl` pe endpoint cu cos mixt (valid, „2,5", „de la", indisponibil, inexistent, 1,5 buc, negativ, dublura) | fiecare rand cu motivul corect; total 201,23 lei |
| Playwright `cos-pagina.spec.js` × 3 latimi: cos gol; flux real din pagini → cos; **pret falsificat in localStorage ignorat**; +/−; zecimale cu virgula; „Scoate" + contor header; produse problema blocheaza comanda; server 503 + „Incearca din nou"; fara scroll orizontal | **29 trecute** |
| API (o data): GET → 405, JSON invalid → 400, fara `items` → 400, corp > 20 KB → 413, `Cache-Control: no-store`, chei identice | trecut |
| Capturi mobil 375 px + desktop 1280 px | doua coloane pe desktop, stivuit pe mobil, randul problema evidentiat |
| Regresie `dev/run_tests.sh` | **287** asertiuni, `php -l` 8.1, 231+264, catalog 534 — trecut |
| Regresie browser completa | **85** trecute, 11 sarite |

**Probleme gasite si rezolvate:**
1. **Raspuns JSON stricat** pentru produsele inexistente (randul nu avea `price_bani`/`line_bani` → avertisment PHP in JSON).
   Acum fiecare rand are aceleasi chei; acoperit de test.
2. `cartProducts()` intorcea `[]` si la eroare de baza → ar fi aparut „produsul nu mai exista" la tot cosul. Acum `null` → 503.
3. **Campul de cantitate refuza „2,5"** (`type="number"` respinge virgula in Chrome cu setari in engleza).
   Trecut pe `type="text"` + `inputmode="decimal"`/`numeric` (tastatura numerica pe telefon), afisare cu virgula.
4. O aserțiune din `test_cart.php` avea o problema de precedenta `&&`/`||` — rescrisa explicit.

---

### T7 — Formular persoana fizica — Finalizat (2026-10-13)

**Obiectiv:** `/comanda.html` cu datele de contact si de livrare, validate, cu erorile afisate langa campuri.

**Fisiere:**
- `includes/order_form.php` (nou) — singurul loc cu regulile:
  - nume 3–100 caractere, litere (cu diacritice), spatiu, cratima, apostrof;
  - telefon romanesc mobil sau fix, in orice scriere uzuala (`0749 130 565`, `+40 749…`, `0040…`, cu puncte/cratime) →
    salvat ca `+40749130565`;
  - email valid, cu domeniu complet, transformat in litere mici;
  - judet dintr-o lista fixa de 42; localitate ≥ 2; adresa ≥ 5; mentiuni optionale, max 1000, cu randuri pastrate;
  - textul e curatat (caractere de control, spatii multiple), taiat la limita pe caractere (nu octeti);
    campuri trimise ca array nu produc erori PHP.
- `comanda.php` (nou) — `noindex`; POST: validare pe server, la erori **422** + formularul reafisat cu valorile introduse,
  mesaj langa fiecare camp (`aria-invalid`, `aria-describedby`) si rezumat focusat. Formular valid → **503** cu mesaj
  onest („trimiterea nu este inca activa") pana la T11 — nu pretindem ca s-a trimis ceva.
  `autocomplete` corect pe fiecare camp (completare automata pe telefon).
- `js/comanda-pagina.js` (nou) — sumarul comenzii de la server; cosul in campul ascuns `cos` (doar id + cantitate);
  verificare rapida in browser cu aceleasi mesaje ca serverul; un singur click pe „Trimite"; trimitere blocata cand
  cosul are produse problema sau serverul nu raspunde; cos gol → fara formular.
- `css/style.css` — formular, sumar; pe mobil sumarul deasupra formularului.
- Teste: `admin/tests/test_order_form.php` (nou, 50), `dev/e2e/tests/comanda-pf.spec.js` (nou).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `test_order_form.php`: 10 telefoane valide + 8 invalide, normalizare, curatare text, diacritice, 19 valori invalide pe camp (inclusiv `<script>`, email cu `\nBcc:`), campuri ca array | **50/50** |
| `curl`: GET 200; POST gol → 422 cu 6 erori; POST valid → 503 cu mesaj; `"><script>` in nume → escapat in `value` | trecut |
| Playwright × 3 latimi: cos gol; sumar de la server (201,23 lei) + campul ascuns; trimis gol → 6 erori, focus pe primul; eroarea dispare dupa corectare; valid → server pastreaza datele (telefon normalizat); cos cu produs indisponibil → buton blocat; fara scroll orizontal, sumar deasupra pe mobil | **21 trecute** |
| Playwright **fara JavaScript**: serverul valideaza singur, 5 erori, valorile pastrate | trecut |
| Capturi mobil + desktop cu erori | erorile sub campuri, lizibile |
| Regresie `dev/run_tests.sh` | **337** asertiuni, `php -l` 8.1, 231+264, catalog 534 — trecut |
| Regresie browser completa | **107** trecute, 13 sarite |

**Probleme:** niciuna; testele au trecut din prima rulare.

**Ramane pentru taskurile urmatoare:** persoana juridica (T8), metoda de plata + transport (T9), CSRF / anti-bot /
limitare / acord GDPR (T10), inregistrarea comenzii (T11).

---

### T8 — Formular persoana juridica — Finalizat (2026-10-13)

**Obiectiv:** comanda poate fi pe firma, cu datele necesare facturarii, validate corect.

**Fisiere:**
- `includes/order_form.php` — `tip` (pf/pj, implicit pf; orice alta valoare sau array → pf); pentru PJ:
  - `orderCui()` — CUI cu sau fara „RO", cu spatii/puncte; **cifra de control** verificata cu algoritmul oficial
    (cheia 753217532); prefixul „RO" se pastreaza daca a fost scris (platitor de TVA);
  - `orderRegCom()` — formatul clasic `J16/123/2020` (si F, C), normalizat (`j 5/0042/2019` → `J05/42/2019`),
    plus formatul unic din 2024 (`J2024000123168`);
  - denumire firma, sediu social; la PF campurile de firma sunt **ignorate complet** (nu ajung in comanda).
- `comanda.php` — alegerea „Comanzi ca: Persoana fizica / juridica", blocul „Date firma", eticheta
  „Persoana de contact" la PJ; dupa o eroare de server formularul revine pe tipul ales.
- `js/comanda-pagina.js` — comutare fara pierderea datelor comune; verificarea rapida include campurile de firma
  doar la PJ; la revenirea pe PF erorile de firma dispar.
- `css/style.css` — optiunile de tip client (cea aleasa evidentiata), `.form-hint`.
- Teste: `admin/tests/test_order_form.php` (+32), `dev/e2e/tests/comanda-pj.spec.js` (nou).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| CUI: 5 valide (inclusiv RO14399840 public, cu spatii, litere mici), 7 invalide (cifra de control gresita, prea lung, litere) | trecut |
| Registrul Comertului: 4 formate valide + normalizare, 6 invalide | trecut |
| PJ valid / PJ gol (4 erori) / mesaj persoana de contact / CUI gresit / PF ignora firma / tip necunoscut sau array | trecut |
| `test_order_form.php` total | **82/82** |
| Playwright × 3 latimi: implicit PF; comutare PF↔PJ fara pierderi; PJ gol → 4 erori + focus; CUI gresit → respins de server, ramane pe PJ cu datele; PJ valid → acceptat, CUI cu RO, RC normalizat | **15 trecute** |
| Captura mobil PJ | blocul de firma clar, optiunea aleasa evidentiata |
| Regresie `dev/run_tests.sh` | **369** asertiuni, `php -l` 8.1, 231+264, catalog 534 — trecut |
| Regresie browser completa | **122** trecute, 13 sarite |

**Probleme:** niciuna in cod. (Un script ajutator de editare n-a gasit textul de inlocuit; testele au fost adaugate direct.)

**De stiut:** fara JavaScript blocul de firma ramane ascuns — acceptabil, pentru ca fara JavaScript nu exista nici cos.
Verificarea CUI la ANAF (firma activa, platitoare de TVA) **nu** e inclusa: ar cere un serviciu extern; o putem adauga separat.

---

### Schimbare de decizie — toate preturile fixe (2026-10-13, intre T8 si T9)

**Cerut de:** utilizator, dupa verificarea locala: „Toate produsele or sa aiba pretul fix… sa putem baga in cos."

**Ce s-a schimbat:**
- **Date locale:** `Fisiere de lucru/Preturi produse - pret fix.xlsx` (copie a estimarilor, cu coloana „De la?" golita;
  fisierul original cu estimari ramane neatins), importat local → **534 produse cu pret fix, 0 „de la"**.
- **Admin:** scoasa bifa „Pret minim"; controllerul salveaza mereu pret fix.
- **Import (`db/import_preturi.php`):** coloana „De la?" nu mai e ceruta; daca exista si contine DA, importul e
  respins cu mesajul „toate preturile sunt fixe — sterge DA din coloana De la?".
- **Export (`Fisiere de lucru/export_preturi.py`):** lista noua nu mai are coloana „De la?".
- Codul de afisare „de la" ramane in `priceLabel()` (coloana `price_is_from` exista in baza), dar nimic nu il mai
  activeaza; poate fi sters complet la cerere.
- **Fixturi de test:** nu mai forteaza preturi fixe; fixeaza doar starile speciale (un produs „La comanda",
  unul „Indisponibil", unul cu 12,49 lei/ml).

**Teste:**

| Test | Rezultat |
|---|---|
| Pagini locale: butoane de cos | **494 / 495** carduri (singurul fara buton: produsul marcat „Indisponibil" in fixturi) |
| „de la" in preturile afisate | **0** |
| `test_import_preturi.php` (DA respins cu mesaj, fisier fara coloana „De la?" acceptat) | 27/27 |
| Export nou | fara coloana „De la?" |
| Playwright: adminul nu mai are bifa; salvarea pastreaza pretul fix si butonul de cos | trecut |
| Regresie `dev/run_tests.sh` | **371** asertiuni — trecut |
| Regresie browser completa | **122** trecute, 13 sarite |

**Problema gasita la test:** prima varianta a testului cauta textul „de la <cifra>" in toata pagina si a prins
fraze editoriale („potrivita pante de la 8°"). Testul verifica acum doar elementele de pret.

**T9 (plata + transport)** era inceput si e pus deoparte (`git stash`), netestat — se reia dupa verificarea ta.

---

### T9 — Plata si mesaje despre transport — Finalizat (2026-10-13)

**Obiectiv:** clientul alege cum plateste; stie clar ce se intampla cu transportul.

**Textele** sunt luate din ce spune deja site-ul (Termeni: „costul si termenul de livrare se stabilesc… la confirmarea
comenzii"; adresa si programul de pe contact) si stau intr-un singur loc, `includes/order_form.php`
(`ORDER_PLATA`, `ORDER_TRANSPORT_INFO`) — **de confirmat cu Mugurel inainte de lansare**.

**Fisiere:**
- `includes/order_form.php` — `plata` obligatorie, din lista fixa: ramburs / transfer bancar (proforma pe email) /
  ridicare si plata in magazin (numerar sau card, Str. Caracal 52, L–V 8–18). **La ridicare adresa de livrare nu
  mai e ceruta si e ignorata** (nu exista livrare).
- `comanda.php` — blocul „Livrare si plata" cu explicatie la fiecare optiune si mesajul de transport; adresa si
  mesajul de transport ascunse la ridicare (si dupa raspunsul serverului).
- `js/comanda-pagina.js` — comutarea adresei; eroarea pentru grupul de optiuni sta **sub lista** si e marcata pe grup
  (`aria-invalid` pe fieldset), nu in interiorul primei optiuni.
- `css/style.css` — optiunile de plata, nota de transport.
- Teste: `test_order_form.php` (+12), `dev/e2e/tests/comanda-plata.spec.js` (nou); `comanda-pf/pj.spec.js` aleg acum o metoda.

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| Cele 3 metode valide; `''`, `card`, `RAMBURS`, array → respinse; ridicare fara adresa → valid si adresa ignorata; transfer cere adresa; fara card online | `test_order_form.php` **94/94** |
| Playwright × 3 latimi: 3 optiuni cu explicatie + mesaj transport; fara metoda → eroare pe grup, sub optiuni; ridicare → adresa si transportul ascunse, valid la server; ramburs → adresa obligatorie; metoda falsificata (`card`) → 422; mesajul de transport si in cos | trecut |
| Captura mobil | optiunea aleasa evidentiata, explicatiile lizibile |
| Regresie `dev/run_tests.sh` | **383** asertiuni — trecut |
| Regresie browser completa | **140** trecute, 13 sarite |

**Probleme gasite si rezolvate:** mesajul de eroare pentru butoanele radio ar fi fost inserat in prima optiune — mutat
sub lista; mesajul „Transportul nu este inclus" nu avea sens la ridicare — ascuns in acel caz.

---

### T10 — Validari si securitate — Finalizat (2026-10-13)

**Obiectiv:** formularul de comanda rezista la trimiteri falsificate, roboti si abuz; clientul isi da acordul GDPR.

**Ordinea verificarilor la POST** (`comanda.php`): limita pe IP → token CSRF → capcana anti-robot → campuri →
**reverificarea cosului pe server**. La orice problema formularul revine cu datele introduse.

**Fisiere:**
- `includes/order_security.php` (nou):
  - sesiune proprie `mug_comanda` (separata de admin), cookie `HttpOnly`, `SameSite=Lax`, `Secure` pe HTTPS;
  - token CSRF de 64 hex, comparat cu `hash_equals`;
  - capcana: camp invizibil `website` + formular trimis in < 3 s de la afisare → respins cu mesaj generic;
  - **IP-ul real din spatele Cloudflare**: `CF-Connecting-IP` acceptat doar daca cererea vine din intervalele
    Cloudflare (IPv4 + IPv6) — altfel antetul poate fi falsificat (aceeasi problema exista azi la login-ul din admin);
  - limita: 10 trimiteri / 10 minute / IP (tabela `order_attempts`, curatata automat); la eroare de baza nu blocam clientul.
- `db/migration_009_order_attempts.sql` (nou, in `deploy_db.php`).
- `admin/config.php` — `ORDER_RATE_MAX`, `ORDER_RATE_WINDOW_SEC`, `ORDER_MIN_FILL_SEC`, suprascriibile local.
  **Local** (`config.local.php`, negituit): limita 1000 si pragul 0 s, pentru testele automate. Pe server raman 10 si 3 s.
- `includes/order_form.php` — acord GDPR obligatoriu (doar valoarea `1`).
- `comanda.php` — token, camp-capcana, bifa de acord cu legaturi spre Termeni si Politica (nebifata din oficiu),
  cosul reverificat (gol / corupt / > 20 KB / produs indisponibil → mesaj rosu cu legatura spre cos), `Cache-Control: no-store`,
  coduri HTTP corecte: 400 (sesiune), 422 (date / robot / cos), 429 (limita).
- `js/comanda-pagina.js` — verificarea acordului; mesajul sta sub text, nu in eticheta. `css/style.css` — capcana ascunsa, blocul de acord.
- Teste: `admin/tests/test_order_security.php` (nou, 44 — unitare + cereri HTTP reale), `test_order_form.php` (+2),
  `dev/e2e/tests/comanda-securitate.spec.js` (nou); testele de comanda bifeaza acum acordul.

**Protectia la injectare in emailuri** (cerinta din plan): toate campurile trec prin `orderText()` (caracterele de control,
inclusiv rand nou, eliminate) si emailul prin `FILTER_VALIDATE_EMAIL` — testat in T7 cu `ion@exemplu.ro\nBcc:`.
Trimiterea propriu-zisa (T12) va folosi PHPMailer, care valideaza si el antetele.

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `ipInCidr` (margini /20, IPv6, amestec v4/v6, text) | trecut |
| IP real: fara CF; antet CF falsificat de un client direct → ignorat; de la Cloudflare v4 si v6 → acceptat; antet invalid | trecut |
| CSRF: format, stabil in sesiune, corect / gol / un caracter diferit / array / lipsa | trecut |
| Capcana (prag explicit 3 s): imediat, 1 s → robot; 4 s, 30 s → om; camp completat / array → robot; doar spatii → om; timp alterat → robot | trecut |
| Limita: 3 trec, a 4-a blocata; alt IP neafectat; dupa fereastra se poate din nou; curatare automata | trecut |
| **HTTP real**: fara token → 400 (datele raman); token din alta sesiune → 400; capcana → 422 generic; fara acord → 422; cos cu produs indisponibil / corupt / prea mare → 422; totul valid → trece; peste limita → **429** | trecut |
| `test_order_security.php` total | **44/44** |
| Playwright × 3 latimi: acord (legaturi, nebifat, eroare la locul ei); capcana invizibila si fara focus; sesiune expirata → mesaj, date pastrate, a doua trimitere trece; cos modificat in pagina → respins de server | trecut |
| `dev/test_fresh_deploy.sh … order_attempts.ip` | tabela exista dupa fiecare rulare |
| Regresie `dev/run_tests.sh` | **429** asertiuni — trecut |
| Regresie browser completa | **152** trecute, 13 sarite |

**Probleme gasite si rezolvate:** primele teste ale capcanei depindeau de pragul local (0 s) si nu puteau pica —
functia primeste acum pragul ca parametru, iar testul verifica explicit pragul de productie de 3 s.

**De stiut pentru lansare (T16):** lista de IP-uri Cloudflare se reverifica; aceeasi functie `orderClientIp()` poate repara
si limitarea login-ului din admin (jurnal, punctul 3).

---

### T11 — Inregistrarea comenzilor si anti-duplicat — Finalizat (2026-10-13)

**Obiectiv:** o comanda valida se salveaza complet (sau deloc) si nu poate fi salvata de doua ori.

**Fisiere:**
- `db/migration_010_orders.sql` (nou, in `deploy_db.php`) — `orders` (date client, plata, livrare, firma, total in bani,
  stare `noua/confirmata/livrata/anulata`, IP, momentul emailurilor) si `order_items` (**copie** a produsului: nume,
  unitate, cantitate, pret, subtotal, stoc — o scumpire ulterioara nu schimba comenzile primite).
- `includes/orders.php` (nou):
  - `orderCreate()` — totul intr-o tranzactie: comanda + produse; la orice eroare → rollback complet;
  - numar `MUG-2026-000042`; token public aleator de 48 hex pentru pagina de confirmare (numarul nu e suficient
    ca sa vezi o comanda);
  - **anti-dublura pe doua niveluri:** (1) token per formular, `UNIQUE` in baza — dublu-click, retrimitere, cereri
    simultane; (2) acelasi continut (client + livrare + produse/cantitati) in 10 minute — „Inapoi" + „Trimite" din nou.
    In ambele cazuri clientul primeste confirmarea comenzii deja salvate.
- `comanda.php` — camp ascuns `idem`; dupa salvare **redirect 303** spre `/comanda-trimisa.html?t=…` (un refresh nu
  retrimite); la eroare de baza: 503, datele raman, mesaj cu telefonul. Token anti-dublura lipsa/falsificat → respins.
- `dev/fixtures/cos_test.sql` sterge comenzile de test; `dev/run_tests.sh` aplica fixturile la inceput.
- Teste: `admin/tests/test_orders.php` (nou, 31), `test_order_security.php` (+1), testele de comanda din browser
  asteapta acum redirectul spre confirmare.

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| Numar, token, amprenta (independenta de ordinea produselor; alta cantitate / alt client → alta amprenta) | trecut |
| Creare: numar, token, total 201,23 lei, datele clientului, 2 produse cu copie exacta (12,49 × 2,5 ml = 31,23) | trecut |
| Copia nu se schimba dupa scumpirea produsului | trecut |
| Acelasi formular de doua ori → aceeasi comanda; formular nou, acelasi continut → aceeasi comanda; o singura comanda in baza | trecut |
| Alt cos → comanda noua; acelasi continut dupa 11 minute → comanda noua | trecut |
| **Rollback:** eroare la scrierea unui produs → nicio comanda, niciun produs orfan, tranzactia inchisa | trecut |
| Cos cu produs indisponibil → refuzat; token public malformat / injectie SQL → null | trecut |
| **HTTP real:** comanda → 303 spre confirmare; a doua trimitere → aceeasi confirmare, 1 comanda in baza; total recalculat pe server (3 × 75 = 225 lei) | trecut |
| `test_orders.php` | **31/31** |
| `dev/test_fresh_deploy.sh orders.form_token order_items.line_bani` | tabelele exista dupa fiecare rulare |
| Regresie `dev/run_tests.sh` | **461** asertiuni — trecut |
| Regresie browser completa | **152** trecute, 13 sarite |

**Probleme gasite si rezolvate:** 4 teste de securitate trimiteau formularul fara noul token anti-dublura si erau
respinse (corect) inainte de verificarea testata — testele trimit acum tokenul; s-a adaugat un test explicit pentru
token lipsa/falsificat.

**De stiut:** pagina de confirmare se construieste in T14; pana atunci redirectul duce la 404 (local).
Lista comenzilor in `/admin` nu face parte din proiect — comenzile ajung pe email (T12) si raman in baza.

---

### T12 — Notificare email catre `lucian@capitalboost.ro` — Finalizat (2026-10-13)

**Obiectiv:** fiecare comanda noua trimite un email cu tot ce trebuie ca sa fie sunat clientul.

**Fisiere:**
- `includes/lib/PHPMailer/` — PHPMailer **6.9.1** (doar `PHPMailer.php`, `SMTP.php`, `Exception.php` + licenta LGPL),
  nemodificat; sursa si SHA-256 in `SURSA.txt`. Fara Composer.
- `admin/config.php` — `APP_ENV` (implicit **local**), `ORDER_NOTIFY_TO` (implicit `lucian@capitalboost.ro`), `MAIL_FROM`,
  `SMTP_*` (implicit Mailpit `127.0.0.1:1025`). Productia le va seta explicit in `config.local.php` (T16).
- `includes/mailer.php` (nou) — `mailSend()` prin SMTP, fara exceptii (esec → log + `false`), timeout 10 s.
  **Garda:** in afara productiei orice adresa `@mugurel-bricolaj.ro` (si subdomenii, orice majuscule) e blocata
  inainte de conectare — nici macar Mailpit nu o primeste.
- `includes/order_mail.php` (nou) — emailul intern: subiect cu numar, total si client/firma; date client (si firma la PJ),
  plata, livrare sau „Ridicare din magazin", mentiuni, tabel produse cu subtotaluri si total, telefon clicabil,
  „Raspunde" merge la client; varianta text pentru clientii de email fara HTML. Tot continutul e escapat.
- `comanda.php` — dupa salvare trimite notificarea **o singura data** (nu la retrimitere); esecul emailului nu
  anuleaza comanda (`email_admin_at` ramane gol — se vede ce n-a plecat).
- Teste: `admin/tests/test_order_mail.php` (nou, 43).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| Garda: adresa de test permisa; `comenzi@mugurel-bricolaj.ro`, majuscule, subdomeniu → blocate local; domeniu asemanator → permis; in productie → permis | trecut |
| Trimitere blocata → `false` si **0 mesaje** in Mailpit | trecut |
| Continut: subiect; XSS in nume/firma/produs/mentiuni escapat; 11 informatii obligatorii prezente; varianta text; PF + ridicare fara date de firma | trecut |
| Integrare Mailpit: 1 mesaj, catre `lucian@capitalboost.ro`, numarul in subiect, Reply-To = clientul, expeditor, HTML si text complete, `email_admin_at` salvat | trecut |
| **SMTP oprit:** `false` fara exceptie, sub 10 s, `email_admin_at` gol, comanda ramane salvata | trecut |
| **HTTP cap-coada:** doua trimiteri ale aceluiasi formular → 303, 303 si **un singur email**; niciun email catre `@mugurel-bricolaj.ro` | trecut |
| Captura emailului (comanda PJ cu transfer) | clar, complet, totaluri corecte |
| Regresie `dev/run_tests.sh` | **504** asertiuni — trecut |
| Regresie browser completa | **152** trecute, 13 sarite |

**Test NEexecutat:** livrarea **reala** catre casuta `lucian@capitalboost.ro` (prin internet). Cere datele unui server SMTP
real (utilizator/parola), pe care nu le am. Local, toate mesajele sunt prinse de Mailpit, conform regulii.
Se poate rula la cerere: se pun datele SMTP in `config.local.php` si se trimite o comanda de test.

**Probleme gasite si rezolvate:** in testul HTTP, cookie-ul de sesiune nu se scria in fisier (in PHP 8 `curl_close()` nu
mai inchide nimic; fisierul se scrie la distrugerea obiectului) → serverul raspundea corect 400 (sesiune lipsa).
Testul a fost corectat; site-ul nu avea nicio problema.

---

### T13 — Email de confirmare catre client (sandbox) — Finalizat (2026-10-13)

**Obiectiv:** clientul primeste imediat confirmarea comenzii; in test, mesajul nu iese din calculator.

**Fisiere:**
- `includes/order_mail.php` — `orderClientEmail()`: „Multumim pentru comanda!", numarul, **ce urmeaza** (te sunam
  L–V 8–18 pentru disponibilitate, cost si termen de livrare / momentul ridicarii), plata aleasa cu explicatia ei,
  adresa de livrare sau a magazinului, produsele si totalul, mesajul de transport (nu si la ridicare), legatura spre
  pagina comenzii (cu token), telefon + WhatsApp, datele firmei (DUNLITE COM SRL, CIF, J, adresa). Fara date interne.
  `orderNotifyClient()` — „Raspunde" merge la magazin; noteaza `email_client_at`; esecul nu afecteaza comanda.
- `comanda.php` — confirmarea pleaca dupa notificarea interna, tot o singura data per comanda.
- Teste: `admin/tests/test_order_mail.php` (+12, total 55).

**Sandbox:** local, SMTP-ul e Mailpit — **toate** mesajele (si cele catre adresele clientilor) sunt prinse acolo
si se vad la http://localhost:8025. Adresele `@mugurel-bricolaj.ro` sunt oricum blocate de garda din T12.

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| HTTP cap-coada: doua trimiteri → **exact doua emailuri** (intern + client), nu patru; niciunul catre `@mugurel-bricolaj.ro` | trecut |
| Client: subiect, Reply-To spre magazin, produse + total + plata, legatura cu token, fara IP/browser, varianta text cu firma si telefon | trecut |
| Comanda noteaza ambele emailuri trimise | trecut |
| Ridicare: adresa magazinului, fara mesaj de transport; transfer: proforma + transport; numele escapat | trecut |
| SMTP oprit la client: `false`, comanda ramane, `email_client_at` gol | trecut |
| Captura emailului clientului (PJ, transfer, 2 produse) | clar, complet |
| Regresie `dev/run_tests.sh` | **516** asertiuni — trecut |
| Regresie browser completa | **152** trecute, 13 sarite |

**De stiut:** legatura din email foloseste `SITE_URL` (`https://mugurel-bricolaj.ro`), deci emailurile de test duc spre
site-ul real, unde pagina inca nu exista. Pe productie va fi corect. Textele emailului — de confirmat cu Mugurel (T16).

---

### T14 — Pagina de confirmare — Finalizat (2026-10-13)

**Obiectiv:** dupa trimitere clientul vede clar ca a reusit si ce urmeaza; cosul se goleste.

**Fisiere:**
- `comanda-trimisa.php` (nou) — `/comanda-trimisa.html?t=<token>`: numarul comenzii, emailul unde a plecat confirmarea,
  „Ce urmeaza" (te sunam la numarul dat; plata aleasa cu explicatia; livrare sau ridicare), produsele si totalul,
  contact (telefon, WhatsApp cu numarul comenzii precompletat). Token gresit/lipsa/malformat → **404** cu mesaj util.
  **Confidentialitate:** adresa contine tokenul secret, deci pagina e `noindex`, `Cache-Control: no-store`,
  `Referrer-Policy: no-referrer` si **nu incarca Google Analytics / Clarity** (ar fi inregistrat adresa cu token).
- `templates/header.php` — optiunea `$skipAnalytics` (implicit analiza ramane pe toate celelalte pagini).
- `js/comanda-trimisa.js` (nou) — goleste cosul **o singura data**, doar dupa o trimitere din acest tab
  (semn in `sessionStorage`, pus de `js/comanda-pagina.js`); legatura redeschisa mai tarziu din email nu goleste un cos nou.
- `js/comanda-pagina.js` — la „Inapoi" dupa trimitere, pagina restaurata din memoria browserului se reincarca
  (altfel ar fi aratat formularul vechi cu „Se trimite…" si cosul plin).
- `css/style.css` — stilurile paginii. Teste: `dev/e2e/tests/comanda-confirmare.spec.js` (nou).

**Teste executate si rezultate:**

| Test | Rezultat |
|---|---|
| `curl`: token valid → 200 cu comanda; zerouri / `abc` / lipsa / `t[]=` / injectie SQL → 404; fara analiza in toate; `no-store` si `no-referrer` | trecut |
| Playwright × 3 latimi — **flux complet din interfata**: buton pe 2 pagini → cos → „Continua comanda" → formular → confirmare: numar, total 109 lei, 2 produse, email, telefon, adresa; cosul golit | trecut |
| Reincarcarea confirmarii: cerere GET, acelasi numar (nu retrimite) | trecut |
| „Inapoi" dupa confirmare: cosul gol, formularul nu mai poate fi trimis | trecut |
| Legatura redeschisa in alt tab cu un cos nou → cosul nou ramane | trecut |
| Ridicare: adresa magazinului, fara mesaj de transport | trecut |
| Token gresit: 404, `noindex`, fara scripturi GA / Clarity | trecut |
| Capturi: confirmare (desktop) si pagina 404 (mobil) | clare |
| Regresie `dev/run_tests.sh` | **516** asertiuni — trecut |
| Regresie browser completa | **170** trecute, 13 sarite |

**Probleme gasite si rezolvate:**
1. „Inapoi" dupa comanda arata pagina veche din memoria browserului → reincarcare automata (`pageshow`).
2. Testul pleca de pe confirmare inainte sa ruleze scriptul ei → testul asteapta incarcarea paginii (un client real nu pleaca in < 100 ms).
3. Un script de captura de ecran se bloca pe mobil; am masurat elementul (pozitie identica in 6 masuratori, nicio animatie
   in afara pulsului butonului WhatsApp) — problema era a scriptului (banner + header peste element), nu a site-ului.

**De stiut (T16):** pe server, `.htaccess` seteaza `Referrer-Policy: strict-origin-when-cross-origin` cu `Header always set`,
care poate suprascrie `no-referrer` din PHP. Si asa, catre alte site-uri se trimite doar domeniul, fara token — fara scurgere.

---

### T15 — Testare integrata si responsive — Finalizat (2026-10-13)

**Obiectiv:** tot fluxul, cap-coada, pe telefon, tableta si desktop; nimic din site-ul existent stricat.

**Fisiere:** `dev/e2e/tests/flux-complet.spec.js` (nou), `dev/e2e/package.json` (+ `@axe-core/playwright` 4.10.1, doar local),
`dev/fixtures/cos_test.sql` (curata si comenzile acestui test), `css/style.css` (culoarea cifrei din campul de cantitate),
`docs/capturi-cos/` (7 capturi selectate, 890 KB; toate cele 36 raman in runner-ul local).

**Ce s-a testat:**

| Test | 375 px | 768 px | 1280 px |
|---|---|---|---|
| **PF + ramburs**: 2 produse din 2 pagini → cos (97,49 lei) → formular → confirmare → 2 emailuri | ✓ | ✓ | ✓ |
| **PJ + transfer**: 3 produse (unul „La comanda") → date firma → confirmare (134,00 lei) → 2 emailuri | ✓ | ✓ | ✓ |
| **PF + ridicare**: subcategorie (`router.php`) → fara adresa → confirmare (180,00 lei) → 2 emailuri | ✓ | ✓ | ✓ |
| Fara scroll orizontal pe formular | ✓ | ✓ | ✓ |
| Doar cu tastatura: buton cos → header → cos → formular; ordinea Tab logica; capcana anti-robot sare | — | ✓ | ✓ |
| Accesibilitate (axe-core) pe cos (cu produs problema) si pe formular (cu erori afisate) | — | — | ✓ 0 probleme |

In fiecare flux emailurile sunt verificate in Mailpit: confirmarea clientului cu subiectul si numarul corect si **o singura**
notificare interna catre `lucian@capitalboost.ro`, cu totalul corect.

**Regresie si alte verificari:**

| Verificare | Rezultat |
|---|---|
| `dev/run_tests.sh` — 11 fisiere de teste PHP | **516** asertiuni trecute |
| `php -l` cu PHP 8.1 (versiunea serverului) pe tot codul | trecut |
| Site existent: 13 pagini 200, 231 materiale + 264 accesorii, catalog 534, JSON-LD, protectia admin | neschimbat |
| `dev/e2e/run.sh` — toate testele in browser | **183** trecute, 18 sarite (intentionat: admin/API/accesibilitate doar pe desktop, tastatura nu pe mobil) |
| `dev/test_fresh_deploy.sh` — deploy pe baza goala, de doua ori, cu toate tabelele noi | trecut |
| Timp de raspuns `cos-produse.php` cu cosul maxim (100 de randuri) | 15–33 ms (local) |
| Marimea scripturilor noi | `cos.js` 6,8 KB, `cos-pagina.js` 8,2 KB, `comanda-pagina.js` 9,6 KB, `comanda-trimisa.js` 0,7 KB (necomprimate) |
| Capturi revazute: cos pe tableta, formular PJ, confirmari | corecte; o singura corectura (culoarea cifrei din cantitate) |

**Probleme gasite si rezolvate:** cifra din campul de cantitate aparea albastra (culoarea implicita a browserului) — setata la culoarea textului.

**Ce NU s-a testat (si de ce):** browsere reale Safari / Firefox (testele ruleaza pe Chromium); livrarea reala de email prin
internet (lipsesc datele SMTP); comportamentul in spatele Cloudflare si pe LiteSpeed (doar pe server). Toate sunt in planul T16.
