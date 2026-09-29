# Jurnal de lucru — Mugurel / Dunlite

Sesiunile de lucru pe site, cea mai recentă prima. Fiecare intrare spune ce s-a rezolvat,
ce a rămas deschis și ce trebuie știut ca să continui fără să redescoperi totul.

---

## ⚠️ CITESTE INTAI DACA RELUI DE PE ALTA MASINA

Lucrarea pe catalog e **live pe mugurel-bricolaj.ro** din 29 septembrie. Orice interventie pe ea cere mediu local.
Inainte de orice, verifica pe masina curenta:

```bash
php --version      # trebuie 8.x
mysql --version    # trebuie 8.x sau MariaDB
```

Daca vreuna lipseste, **instaleaza Laragon Full** (https://laragon.org/download/ — include
PHP, MariaDB/MySQL si Apache), porneste-l cu **Start All**, apoi adauga in PATH (User):

- `C:\laragon\bin\php\php-<versiune>-Win32-vs16-x64`
- `C:\laragon\bin\mysql\mysql-<versiune>-winx64\bin`

Numele exacte ale folderelor difera de la o instalare la alta — citeste-le din
`C:\laragon\bin\php\` si `C:\laragon\bin\mysql\`, nu le ghici.

Apoi refa baza locala (vezi „Cum refaci mediul local" mai jos). **Baza de date NU e in git** —
pe o masina noua e goala si trebuie reconstruita din scripturi.

---

## 29 septembrie 2026 — LIVE pe mugurel-bricolaj.ro

Catalogul pe baza de date **ruleaza in productie**. 46 de commit-uri pe `feat/catalog-db`,
ramura de pe care deployeaza cPanel momentan.

### Ce e live acum

| | Inainte | Acum |
|---|---|---|
| Catalog | 84 produse hardcodate | **534**, paginate cate 60 |
| Pagini de categorie | HTML static | toate 13 din baza de date |
| Produse in baza de productie | 231 (resturi din iunie) | **534** |
| Blocuri de proprietati | 0 | **464** |
| Subcategorii cu produse | 0 din 17 | **17 din 17** |
| Panoul `/admin` | fara efect asupra site-ului | **functional cap-coada** |

Verificat direct pe `mugurel-bricolaj.ro`, pagina cu pagina: **0 pagini non-200**,
**231 materiale + 264 accesorii** (identic cu local), JSON-LD prezent pe toate 13,
catalogul la 534, adminul 200.

Mugurel poate acum sa adauge un produs din `/admin`, sa aleaga pagina si sectiunea, sa incarce
o fotografie si sa scrie blocurile de specificatii si avantaje — si produsul apare pe site.

---

## Sistemul de siguranta — de citit inainte de urmatorul deploy

Deploy-ul a reusit din a **treia** incercare. Primele doua au picat, fiecare din alta cauza.
Ce a facut diferenta n-a fost ca am nimerit-o a treia oara, ci ca fiecare esec a fost
**vizibil, izolat si reversibil**. Mecanismele de mai jos raman in cod si merita intelese.

### 1. Degradare eleganta — pagina nu moare cand baza tace

Toate functiile din `Website/includes/products_repo.php` returneaza `[]` la orice eroare de
baza de date, niciodata exceptii. Paginile randeaza atunci tot continutul editorial — JSON-LD,
titluri, texte, sectiuni, FAQ — si pun in locul grilelor un mesaj catre WhatsApp.

La al doilea deploy esuat, exact asta s-a intamplat: site-ul a ramas **200 pe toate paginile**,
cu tot continutul indexabil, doar fara produse. Fara plasa asta, ar fi fost 500 peste tot si
paginile ar fi inceput sa cada din Google.

Regula: **niciodata 500**. Un site cu grile goale se repara; unul disparut din index, mult mai greu.

### 2. Verificare prin interogarea site-ului real, nu prin raport

Dupa fiecare deploy, starea se verifica **interogand direct site-ul live**, nu citind ce spune
cineva ca s-a intamplat:

```bash
for p in acoperis izolatie gips-carton zidarie-bca gard-imprejmuiri electrice incalzire \
         sanitare apa-canal gradina mobilier electrocasnice scule-unelte; do
  c=$(curl -s "https://mugurel-bricolaj.ro/$p.html")
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://mugurel-bricolaj.ro/$p.html")
  m=$(echo "$c" | grep -o 'class="material-card"' | wc -l)
  a=$(echo "$c" | grep -o 'class="accessory-card' | wc -l)
  j=$(echo "$c" | grep -c 'application/ld+json')
  printf "%-18s %5s mat=%-4s acc=%-4s jsonld=%s\n" "$p" "$code" "$m" "$a" "$j"
done
```

Numerele asteptate: **231 materiale + 264 accesorii**, toate 200, JSON-LD pe fiecare pagina.
Catalogul: `curl -s .../catalog.html | grep -o 'din [0-9]* produse'` → `din 534 produse`.

**Numara aparitiile cu `grep -o | wc -l`, nu liniile cu `grep -c`** — mai multe carduri pot sta
pe acelasi rand, iar `grep -c` da rezultate gresite. Din cauza asta am crezut initial ca sunt
227 de produse in loc de 231.

Asta a prins fiecare problema in cateva secunde, inainte sa apuce cineva sa se uite pe site.

### 3. Log de deploy — nimic nu mai esueaza in tacere

`.cpanel.yml` ruleaza `db/deploy_db.php` si scrie **tot** output-ul in
`public_html/__deploy_m7x2verif.log`, citibil prin browser.

A doua incercare a picat fiindca aveam `|| true` la capatul comenzii, fara sa scriu nicaieri ce
s-a intamplat: deploy-ul a raportat succes, iar baza a ramas neatinsa. Log-ul a transformat a
treia depanare din ghicit in citit:

```
=== folosesc /opt/cpanel/ea-php81/root/usr/bin/php (8.1.34) ===
Could not open input file: db/deploy_db.php
```

Doua randuri, cauza exacta.

**Citeste log-ul dupa fiecare deploy:** `https://mugurel-bricolaj.ro/__deploy_m7x2verif.log`

### 4. Garda pe date — un redeploy nu sterge ce adauga Mugurel

`productie_date.sql` contine `DROP TABLE`. Rulat la fiecare deploy, ar sterge tot ce se adauga
din admin. Garda din `deploy_db.php` importa **doar** cand tabela `products` e goala **sau** cand
niciun rand nu are `page_slug` setat.

`page_slug` e coloana care decide pe ce pagina apare produsul. Randurile dinaintea migrarii o au
goala; orice produs adaugat din noul admin o are completata — deci e protejat automat.

Asta a contat: productia avea **231 de randuri vechi** dintr-un import din iunie. Cu garda
initiala („importa doar daca e goala"), importul ar fi fost sarit si site-ul ar fi pornit cu date
vechi fara `page_slug`, adica grile goale peste tot. Log-ul de la deploy-ul reusit arata garda
lucrand:

```
Curat 231 randuri vechi din `products` (fara page_slug, dintr-un import anterior).
OK — date importate din productie_date.sql
```

Testat pe trei scenarii inainte de deploy: date vechi → inlocuite; produs adaugat din admin →
protejat; rulare repetata → idempotent.

### 5. Revenire in doua click-uri

cPanel → Basic Information → dropdown pe **`main`** → Update → Pull or Deploy → **Deploy HEAD Commit**.

Site-ul revine la versiunea dinainte in ~30 de secunde. A functionat de doua ori. Fisierele `.html`
vechi revin din repo, `.htaccess`-ul vechi nu mai are regula de rewrite, iar tabelele noi raman in
baza fara sa deranjeze — codul vechi nu le atinge.

**De asta deployezi un branch, nu `main`.** Revenirea e o selectie din dropdown, nu force-push si
rescriere de istoric.

### 6. Diagnostic pe un branch identic cu productia

Cand a trebuit sa aflam versiunea reala de PHP de pe server, am creat branch-ul `diag` =
`main` + **un singur fisier**, `__diag.php`. Deploy-ul de pe el nu schimba site-ul, doar adauga
fisierul. Protejat cu token in URL (`?t=...`), 404 fara el.

A raspuns in 10 secunde la intrebari la care altfel am fi dedus gresit: PHP **8.1.34 pe LiteSpeed**,
toate cele 7 verificari de sintaxa OK, si starea exacta a tabelelor.

Fisierul se sterge automat la urmatorul deploy real (`rm -f $DEPLOYPATH/__diag.php` in `.cpanel.yml`).

---

## Cele trei esecuri si ce le-a cauzat

Toate trei au fost greseli de acelasi tip: **am presupus in loc sa verific**, iar de doua ori am
scris presupunerea ca pe un fapt, direct in comentariile din cod.

**1. Adaptare de mediu local intr-un fisier de productie.**
Pusesem in `.htaccess` un bloc `<IfModule php_module>` cu `RemoveHandler .php`, ca PHP-ul sa
ruleze pe Laragon. In comentariu scria: „pe cPanel `php_module` nu exista, deci nu se aplica".
Nu verificasem niciodata. LiteSpeed **nu evalueaza `<IfModule>` ca Apache** — a executat
continutul oricum, a sters handlerul `ea-php81`, iar fisierele au ajuns pe un PHP vechi care
crapa pe orice sintaxa 7.4+.

**Regula:** nicio adaptare de mediu local in fisiere care ajung pe server. Nevoile locale stau in
vhost-ul Laragon (`C:\laragon\etc\apache2\sites-enabled\mugurel.conf`), cu `AllowOverride None`,
care ignora complet `.htaccess`-ul de productie si replica doar regulile necesare in dezvoltare.

**2. `php` din linia de comanda nu e acelasi cu handlerul web.**
Pe cPanel, `php` poate fi orice versiune. `.cpanel.yml` cauta acum explicit un binar PHP 8:
`/opt/cpanel/ea-php81/root/usr/bin/php`, apoi alternative, verificand versiunea inainte de a-l folosi.

**3. Taskurile de deploy ruleaza in acelasi shell.**
Un task anterior face `cd $DEPLOYPATH`, deci pasii urmatori pornesc din `public_html`, nu din
clona git. `db/deploy_db.php` nu era gasit. Rezolvat cu `export REPOPATH=$PWD` **inainte** de
orice `cd`, si cale absoluta la invocare.

---

## Ramase de facut

- [ ] **Merge `feat/catalog-db` in `main`.** cPanel deployeaza acum de pe branch, ceea ce
      functioneaza, dar pe termen lung `main` ar trebui sa fie ramura de productie.
- [ ] **Cele 31 de fotografii** cu calea deja configurata in baza — lista completa e in intrarea
      din 28 septembrie. Pui fisierul in `Website/img/` cu numele exact si poza apare singura,
      peste panoul generat, fara nicio modificare de cod.
- [ ] **Sterge `__deploy_m7x2verif.log`** din `public_html` cand nu mai e nevoie de el.
- [ ] **Cele 3000+ de produse** din gestiune, cand exista digital. Repository-ul accepta deja
      `limit`/`offset` si catalogul e paginat; lipseste doar importul.


---

## 28 septembrie 2026 (seara) — Aspect si viteza: cinci reparatii vizuale

Continuare a zilei. Lucrarea pe baza de date era terminata; astea sunt corectii de aspect si
performanta, gasite uitandu-ne efectiv la site in browser, nu in cod.

**Commit-uri:** 5 in `Website`, plus pointerii in parinte. Total nepushuit: **64** in parinte,
**39** in `Website`. Teste: **198** de asertiuni, toate trec.

### 1. Poza urmeaza produsul pe orice pagina

Catalogul randa doar iconite, niciodata fotografia — asa fusese dintotdeauna, dar cu 84 de produse
nu se observa. Acum, cu 534, un produs aparea cu poza pe pagina lui de categorie si cu un desen in
catalog. Reparat: cardul de catalog randeaza `<img>` cand exista, cu aceeasi plasa de siguranta
`onerror`.

### 2. Cele 31 de fotografii lipsa (RAMAS DESCHIS)

Din 75 de produse cu `image_path` setat, **44 au fisierul, 31 nu**. Lipsesc si de pe live —
verificat, dau 404 pe `mugurel-bricolaj.ro`. Nu e ceva stricat de noi: paginile au referit
dintotdeauna fisiere care n-au fost urcate, iar `onerror` ascundea imaginea rupta.

**Cel mai ieftin castig cand ai timp:** calea si alt-text-ul sunt deja in baza. Pui fisierul in
`Website/img/` cu numele exact si poza apare singura, peste tot, fara cod.

| Pagina | Fisiere lipsa |
|---|---|
| Acoperis | `tabla-zincata.jpg` |
| Gard | `panou-gard-duplex.jpg`, `panou-gard-greu.jpg` |
| Electrice | `cablu-myym.jpg`, `cablu-cyy.jpg`, `conductor-fy.jpg`, `cablu-mccg.jpg` |
| Incalzire | `centrala-gaz.jpg`, `centrala-peleti.jpg`, `centrala-electrica.jpg`, `boiler-electric.jpg` |
| Sanitare | `vas-wc.jpg`, `lavoar.jpg`, `chiuveta-bucatarie.jpg`, `bideu-ceramic.jpg` |
| Gradina | `furtun-armat-12.jpg`, `furtun-armat-34.jpg`, `irigatie-picatura.jpg`, `aspersor-rotativ.jpg` |
| Mobilier | `pal-melaminat.jpg`, `mdf-vopsit-alb.jpg`, `pal-hidrofug.jpg`, `plinta-mdf.jpg` |
| Electrocasnice | `boiler-80l.jpg`, `boiler-100l.jpg`, `boiler-120l.jpg`, `boiler-50l.jpg` |
| Scule | `bormasina-percutie.jpg`, `polizor-unghiular.jpg`, `fierastrau-circular.jpg` |

**Acoperirea reala azi: 44 de fotografii din 534 de produse (8%).** 459 nu au poza deloc.

### 3. Panou generat pentru cardurile fara fotografie

Problema nu era placeholder-ul in sine, ci ca **iconitele se repeta identic**: pe `apa-canal`
toate cele 18 produse aveau acelasi desen, pe `electrice` unul singur aparea de 19 ori din 23.
Iconitele n-au fost niciodata per produs — sunt aproximativ per sectiune.

Solutia aleasa: fiecare produs fara poza primeste un **panou generat determinist din numele lui** —
culoare si forme calculate din `md5(nume)`, deci mereu identic pentru acelasi produs si practic
niciodata la fel intre produse. Pe `apa-canal`, de la 1 desen repetat la **7 culori distincte**.

Panoul e si plasa de siguranta pentru pozele lipsa: `onerror` ascunde acum doar `<img>`-ul, nu tot
blocul, deci ramane panoul in loc de o gaura. Trei situatii, un singur aspect consecvent.

Iconitele nu se mai randeaza in zonele de imagine. `icon_key` si `icon_label` **raman in baza** —
doar nu se afiseaza.

Cost: +22KB de HTML pe catalog (60 de panouri inline). Comprimat de Cloudflare, neglijabil.

### 4. Interogari: de la 30 la 6 per pagina

Fiecare apel `section()` facea doua interogari — una pentru produse, una pentru proprietati.
Masurat: `izolatie` **30**, `acoperis` 24, `sanitare` 21.

Acum pagina isi incarca tot continutul intr-o singura interogare (plus una pentru proprietati) si
imparte in PHP, prin `pageProductsIndexed()`. Arhitectural sunt **doua interogari logice**
indiferent de cate sectiuni are pagina; restul de 4 sunt costul fix al conexiunii, pe care il
plateste si catalogul.

Local diferenta nu se simte (TTFB 65-197ms). Pe cPanel, unde fiecare interogare costa mult mai
mult, se va vedea.

In plus: `loading="lazy"`, `decoding="async"` si `width`/`height` pe toate imaginile de produs —
nu se mai incarca pozele de jos pana ajungi la ele, si pagina nu mai sare cand intra imaginile.
Logo-urile au ramas cu `eager`, corect.

### 5. Latimea sectiunilor — bug vechi, prezent si pe live

Pe fiecare pagina, primele 5-6 sectiuni `.cat-section-block` stateau in `<main class="main-content">`
(coloana de langa sidebar), iar ultimele 2-4 erau **dupa** `</main>`, fara nicio limita de latime —
se intindeau pana la marginea ecranului.

Verificat pe `mugurel-bricolaj.ro`: **exista si acolo, identic**. Nu l-am introdus noi.

Reparat in doua etape:
1. CSS — `.cat-section-block` capata `max-width: 1280px`, anulat pentru cele deja din `.body-wrap`.
   A rezolvat iesirea pe toata latimea, dar mai ramanea o diferenta de 250px la stanga
   (latimea sidebar-ului plus gap).
2. HTML — sectiunile ramase in afara au fost mutate in `<main>`, cu un script care a comparat
   continutul inainte si dupa (identic, doar pozitia in arbore s-a schimbat). Acum toate sunt
   copii ai aceluiasi container, deci primesc aceeasi cutie.

`faq-section` si `.container` au ramas in afara, corect — au fundal propriu pe toata latimea.

**Verificat dupa:** 0 sectiuni in afara coloanei pe toate cele 13 pagini, 231 materiale +
264 accesorii neschimbate, 6 interogari per pagina.

### De retinut pentru data viitoare

Am reparat pe rand inaltimea, culoarea, eticheta duplicata si iconitele repetate inainte sa-mi
dau seama ca intrebarea era despre **latimea containerului**. Toate erau probleme reale, dar
niciuna nu era cea semnalata. Cand ceva „arata la fel dupa ce ai reparat", nu inseamna ca fixul
n-a mers — inseamna ca ai reparat altceva.

Verificarea corecta ar fi fost sa ma uit la structura paginii de la inceput, nu la stilul cardului.


---

## 28 septembrie 2026 (zi) — Catalog pe baza de date: TERMINAT (local), gata de deploy

**Branch:** `feat/catalog-db` in ambele repouri. **58 de commit-uri** in parinte, **34** in `Website`.
**Nimic nu e impins pe GitHub. Nimic nu e pe site.** `master`/`main` sunt neatinse.

Toate cele 12 taskuri sunt complete si verificate local. Ce urmeaza e exclusiv deploy-ul.

### Ce functioneaza acum, local

| | Inainte | Acum |
|---|---|---|
| Produse in baza de date | 0 | **534** |
| Catalog | 84, hardcodate | **534**, din baza, paginat cate 60 |
| Pagini de categorie | HTML static | toate 13 din baza |
| Subcategorii cu produse | 0 din 17 | **17 din 17** |
| Panoul `/admin` | fara efect asupra site-ului | **adauga, editeaza, sterge — se vede pe site** |
| Teste automate | 3 | **160** |

Verificabil la `http://localhost/` (Apache din Laragon serveste `Website/` in radacina).
Admin: `http://localhost/admin/`, `Mugurel-Bricolaj` / `bricolaj1234!`.

### Structura finala

**Conventie de cai in jurnalul asta:** toate caile sunt relative la radacina proiectului
(parintele lui `Website/`), cu prefixul `Website/`. Comenzile de mai jos (bash) se ruleaza
din radacina proiectului, nu din interiorul `Website/` — daca rulezi din interiorul
`Website/`, scoate prefixul `Website/` din fiecare cale. `docs/` si `db/` traiesc acum
in `Website/docs/` si `Website/db/` (mutate din repo-ul parinte, versionate pe GitHub odata
cu restul site-ului).

- `Website/*.php` — cele 13 pagini de categorie plus `catalog.php`. URL-urile publice raman
  `.html`, printr-un rewrite din `.htaccess`. Zero redirect-uri, zero impact SEO.
- `Website/includes/` — `products_repo.php` (doar SQL), `product_grid.php` (doar randare),
  `product_icons.php` (127 de iconite, generat), `page_bootstrap.php` (prologul paginilor)
- `Website/db/migration_002..006` — ierarhia de categorii, proprietatile, fotografiile si badge-urile,
  accesoriile, mesajele WhatsApp
- `Website/db/migrate_products.php` si `Website/db/migrate_catalog_products.php` — scripturi de **bootstrap**,
  nu de intretinere; vezi avertismentul de mai jos

Au ramas statice, corect, doar paginile fara produse: `index`, `contact`, `despre-noi`,
cele trei de politici si `termeni-conditii`.

### Ce a iesit la iveala pe parcurs

Planul initial presupunea 227 de produse intr-o structura simpla. Realitatea a fost alta, si
fiecare abatere a fost gasita pentru ca subagentii au comparat cu HTML-ul real in loc sa creada
brief-ul. In ordine cronologica:

1. **Numarul real de produse e 231, nu 227.** Cifra gresita era numarul de carduri *cu iconita*.
2. **`cat_slugs` nu urca prin `parent_id` la produsele cross-listate.** Latent, dar ar fi facut
   un produs invizibil pentru filtrul categoriei lui — exact bug-ul de la care plecasem.
3. **Schema pierdea 80% din continutul produselor.** Cardurile nu au o descriere, ci **N blocuri**
   (`Avantaje` cu bullet-uri, `Specificatii`, `Aspect`, `Utilizare`, `Unde se foloseste`) —
   464 de blocuri pe 231 de produse. A aparut tabelul `product_properties`.
4. **Fotografiile si badge-urile lipseau din schema.** 50 de produse au poza reala, 33 au badge.
5. **264 de `accessory-card` nu fusesera vazute deloc.** Tot produse, in format simplu.
   Inventarul real era dublu fata de ce credeam.
6. **310 produse au mesaj WhatsApp scris de mana**, diferit de sablon. Randarea generica le-ar
   fi inlocuit pe toate.
7. **4 sectiuni contin mai multe grile sub acelasi titlu.** Solutia initiala — feliere pozitionala
   cu `limit`/`offset` — a fost respinsa: punctul de rupere s-ar fi deplasat cand cineva adauga
   un produs. Fiecare grila are acum propria cheie.
8. **40 de produse existau DOAR in `catalog.html`** — cele adaugate in august ca sa umple
   subcategoriile goale. Ar fi disparut de pe site. Tot ele explicau de ce toate cele 17
   subcategorii erau goale in baza: informatia de subcategorie traia doar in `data-subcat`.
9. **Sidebar-ul trimitea la catalog filtrat in loc de paginile dedicate.** Regresie prinsa la
   review — paginile acelea au ~1400 de linii de continut editorial, sunt cele mai valoroase
   pentru cautarile locale.

### Doua capcane de retinut

**`Website/db/extract_icons.php` era o bomba.** Citeste SVG-uri din `Website/<pagina>.html`. Dupa
conversia paginilor la `.php`, fisierele nu mai exista — rulat asa cum era, a suprascris
biblioteca cu **zero iconite**, ceea ce ar fi spart tot site-ul. Acum citeste din git si
**refuza sa scrie daca numarul de iconite ar scadea**. Protectia a fost testata.

**Scripturile din `Website/db/` sunt bootstrap, nu intretinere.** Odata o pagina convertita la `.php`,
sursa ei `.html` nu mai exista, deci nu mai poate fi reimportata. Am verificat ce se intampla
daca cineva ruleaza `migrate_products.php` azi: sare peste paginile convertite si **nu sterge
nimic** (are un guard care refuza stergerea cand n-a atins niciun rand). Cele 534 de produse
raman intacte. De acum **baza e sursa de adevar**.

### Cum arata verificarea

Fiecare task a trecut prin implementare, apoi review independent, apoi verificare facuta de mine.
Probele care conteaza:

- **Invelisul editorial identic octet cu octet.** Pentru fiecare din cele 13 pagini, HTML-ul
  livrat de Apache a fost comparat cu originalul din git, neutralizand continutul grilelor.
  Rezultat identic — JSON-LD, H1, texte, headere de sectiune si FAQ neatinse.
- **Degradare eleganta.** Cu MySQL oprit, paginile raspund tot **200**, cu tot continutul
  editorial si un mesaj catre WhatsApp in locul grilelor. Niciodata 500. Fara asta, un hiccup
  de baza de date ar scoate paginile din Google tacut, luni de zile.
- **Parametri invalizi.** `?page=abc`, `?page=-5`, `?page=999`, categorii inexistente, payload
  XSS in cautare — toate raspund 200 si sunt escapate corect.
- **160 de teste automate**, de la 3 cate erau.

### CE A RAMAS DE FACUT — deploy, in ordinea asta

- [ ] **1. `git push`** — branch-ul nu are upstream, deci prima data:
      `git push -u origin feat/catalog-db` in ambele repouri (intai `Website/`, apoi parintele).
      **Se cere acordul utilizatorului inainte.**
- [ ] **2. Merge in `main`/`master`** dupa ce te uiti peste diff pe GitHub.
- [ ] **3. cPanel → Git Version Control → Deploy HEAD Commit.**
      Push-ul singur NU pune nimic pe site.
- [ ] **4. Cloudflare → Caching → Purge Everything**, apoi Ctrl+Shift+R.
      `js/` si `css/` sunt cache-uite 4 ore.
- [ ] **5. Pe server, o singura data:** aplica migrarile pe baza de productie, in ordine —
      `migration_002` … `migration_006` — apoi `Website/db/migrate_products.php` si
      `Website/db/migrate_catalog_products.php` (prin browser sau SSH), si **sterge-le dupa** daca
      le-ai urcat in `public_html`.
- [ ] **6. Verifica pe server:** `php -l` pe fisierele noi (serverul ruleaza **PHP 8.1**, local
      am avut 8.3 — nu am putut verifica prin executie), apoi cateva pagini si catalogul.
- [ ] **7. Test in admin pe productie:** adauga un produs, vezi-l pe site, sterge-l.

### Ramase deschise, neblocante

- **Cele 3000+ de produse** din gestiune, cand exista digital. Infrastructura le suporta deja —
  repository-ul accepta `limit`/`offset` si catalogul e paginat. Importul ramane de scris.
- **Iconitele accesoriilor fara imagine.** 223 de accesorii nu au zona de imagine nici in
  HTML-ul original; in catalog primesc iconita generica. Comportament corect, dar se poate
  rafina.
- **Doua reguli `.pagination` in `css/style.css`** — cea veche (linia ~202, cu `.pg-btn`, care
  se foloseste) si una adaugata de mine si eliminata ulterior. De verificat ca a ramas doar una.
- **Un bloc CSS formatat multi-linie** (`.cat-empty-note`) intr-un fisier care foloseste reguli
  pe o singura linie. Cosmetic.
- **`categories.parent_id` nu impiedica structural un al treilea nivel** sau un ciclu. Relevant
  daca adminul va putea crea categorii.

### Reguli, reconfirmate

- **Fara `Co-Authored-By: Claude`** sau orice mentiune Claude/Anthropic, nicaieri.
- **Fara `git push` fara acord explicit**, de fiecare data.
- Modificarile se vad **intai local**, inainte de orice deploy.


---

## 27 august 2026 — Catalog pe baza de date (inceput; finalizat pe 28 septembrie)

**Branch:** `feat/catalog-db` in **ambele** repouri (parinte si submodulul `Website/`).
**Nimic nu e impins pe GitHub. Nimic nu e pe site.** `master`/`main` sunt neatinse.

**Documente:**
- Spec: `Website/docs/superpowers/specs/2026-08-27-catalog-db-design.md`
- Plan (10 taskuri): `Website/docs/superpowers/plans/2026-08-27-catalog-db.md`
- Progres live: `.superpowers/sdd/progress.md` — **gitignorat, nu ajunge pe alta masina**.
  Foloseste jurnalul asta si `git log` ca sa te orientezi.

### Ce problema rezolvam

A plecat de la o intrebare simpla: „de ce catalogul arata doar 84 de produse?". Nu era un bug
de filtrare — `catalog.html` contine exact 84 de carduri scrise de mana si nu atinge baza de
date. Sub el stateau trei probleme mai mari:

1. **Trei liste de produse care nu comunica.** `catalog.html` avea 84, paginile de categorie
   au **231**, baza de date era goala. Filtrul „Electrice" din catalog arata 11 produse, dar
   `electrice.html` are 23.
2. **Panoul `/admin` nu avea NICIUN efect asupra site-ului.** In `.htaccess`, regula de
   passthrough pentru fisiere reale (`RewriteCond %{REQUEST_FILENAME} -f`) ruleaza inaintea
   rewrite-ului catre `router.php`, iar `.cpanel.yml` copiaza `*.html` in `public_html/`. Deci
   `electrice.html` exista fizic, Apache il servea static, si `router.php` nu era apelat
   niciodata. Tot CMS-ul construit in iunie era deconectat de la vizitator.
3. **Schema din iunie nu suporta ce facea deja frontendul** — fara subcategorii, fara produse
   in categorii multiple, fara randul `constructii`, cu slug-uri divergente si diacritice in nume.

### Solutia aleasa (decizii luate impreuna, nu presupuneri)

- Baza de date devine sursa de adevar **doar pentru produse**. Invelisul editorial (JSON-LD,
  H1, texte, FAQ, headerele de sectiune) ramane in fisiere. Activarea oarba a `router.php` ar
  fi inlocuit pagini de ~1400 de linii cu template-uri de 55 — sinucidere SEO.
- Paginile devin `.php`, dar **URL-urile publice raman `.html`** printr-un rewrite. Zero redirect-uri.
- Adminul gestioneaza **doar produsele**, nu textele editoriale.
- Pilot pe o singura categorie (Electrice), validat, apoi replicare pe restul de 12.

### Ce e GATA (comis local, trecut prin review)

| Task | Continut | Commit-uri |
|---|---|---|
| 1 | Migrarea schemei: `parent_id`, `product_categories`, `page_slug`, `section_key`, `icon_key`, `icon_label`, cheia unica `uq_page_section_name` | parinte `f88e085` + `0b76eca`, Website `ee744a3` |
| 2 | Biblioteca de 66 de iconite SVG extrase automat din HTML | parinte `26be8a5`, Website `4f093fe` |
| 5 | Import idempotent: **231 de produse** in DB, cu pagina, sectiune si iconita | parinte `998062c` |

Plus configuratia de mediu local: Website `43c50b2`, parinte `bae5a9f`.

**Starea bazei locale:** 31 categorii (9 de nivel 1, 22 de nivel 2), 231 produse, 2 legaturi
cross-listate. Tabelul `product_properties` **inca nu exista**.

### Ce a iesit din review-uri (lucruri prinse la timp)

- **Migrarea pretindea idempotenta fara sa o aiba.** `ALTER TABLE ADD COLUMN` crapa la a doua
  rulare. Ar fi lasat baza de productie pe jumatate migrata, fara cale de reluare. Reparat cu
  proceduri ghidate de `information_schema`; verificat prin dubla rulare si pe o baza noua.
- **Numarul real de produse e 231, nu 227.** 227 era numarul de carduri *care au iconita*; 4
  carduri din `apa-canal.html` folosesc alt markup. Cifra gresita se propagase in spec si plan.
- **`CROSS_LISTED` folosea un nume care exista doar in `catalog.html`**, nu in paginile de
  categorie care sunt sursa migrarii. Cross-listarea nu ar fi pornit niciodata si nimeni nu ar
  fi observat. Numele corect: `Chiuveta Bucatarie Inox 1 Cuva 60cm`, din `sanitare.html`, cu
  categoria suplimentara `mobilier`.
- **Cel mai grav: schema pierdea 80% din continutul produselor.** Cardurile nu au o singura
  descriere, ci **N blocuri de proprietati** — 187 din 231 (80%) au mai multe: `Avantaje`
  (liste cu bullet-uri, 187), `Specificatii` (141), `Aspect` (43), `Utilizare` (31),
  `Unde se foloseste` (18). Importul pastra doar primul bloc. Decizia luata: **tabel
  `product_properties`**, adaugat in plan ca Task 5B.

### CE URMEAZA — reia exact de aici

Ordinea a fost schimbata fata de plan: **Task 5 a rulat inaintea lui Task 3**, fiindca testele
lui Task 3 asserteaza numaratori care au nevoie de date deja importate.

- [ ] **Task 5B — `product_properties`** (URMATORUL). Creeaza `Website/db/migration_003_properties.sql`
      si extinde `Website/db/migrate_products.php`. Verificarea care conteaza: distributia etichetelor
      trebuie sa fie exact `Avantaje 187, Specificatii 141, Aspect 43, Utilizare 31,
      Unde se foloseste 18`.
- [ ] **Task 3** — `Website/includes/products_repo.php` (doar SQL, cu degradare eleganta la
      caderea DB) + teste
- [ ] **Task 4** — `Website/includes/product_grid.php` (doar randare, doua variante de card) + teste
- [ ] **Task 6** — pilot `electrice.php` + regula din `.htaccess` + `includes/page_bootstrap.php`
- [ ] **Task 7** — restul de 12 pagini, commit separat per pagina
- [ ] **Task 8** — `catalog.php`: aici se rezolva efectiv **84 → 231**
- [ ] **Task 9** — campurile noi in admin (fara el, adminul ramane read-only)

Toate au cod complet in plan. Abaterile de la plan de pana acum sunt notate mai sus.

### Cum refaci mediul local (pe orice masina)

Baza de date nu e in git. Dupa ce ai Laragon:

```bash
PHP=/c/laragon/bin/php/php-<versiune>/php.exe
MYSQL=/c/laragon/bin/mysql/mysql-<versiune>/bin/mysql.exe

$MYSQL -u root -e "CREATE DATABASE IF NOT EXISTS mugurel_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQL -u root mugurel_cms < Website/db/schema.sql
$MYSQL -u root mugurel_cms < Website/db/migration_002_hierarchy.sql
```

`Website/admin/config.local.php` nu e in git — recreeaza-l cu:

```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mugurel_cms');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Apoi userul admin si importul:

```bash
HASH=$($PHP -r 'echo password_hash("bricolaj1234!", PASSWORD_BCRYPT, ["cost"=>12]);')
$MYSQL -u root mugurel_cms -e "INSERT IGNORE INTO users (username,email,password_hash,role) VALUES ('Mugurel-Bricolaj','admin@mugurel-bricolaj.ro','$HASH','admin');"

$PHP Website/db/migrate_products.php            # importa cele 231 de produse
$PHP Website/admin/tests/test_db.php    # trebuie 5 passed, 0 failed
```

**Preview in browser.** Leaga folderul in Laragon si reincarca Apache **din interfata Laragon**
(ruleaza ca proces, nu ca serviciu — nu se reporneste din linia de comanda):

```
cmd /c mklink /J "C:\laragon\www\mugurel" "<cale>\Website"
```

Apoi `http://localhost/mugurel/`. Pentru `/admin` e nevoie ca site-ul sa fie in radacina
domeniului, fiindca codul redirectioneaza catre `/admin/?page=login` (cale absoluta). Exista
un vhost pregatit la `C:\laragon\etc\apache2\sites-enabled\mugurel.conf` care serveste site-ul
pe `http://localhost/` — pe o masina noua trebuie recreat, nu e in git.

Credentiale admin local: `Mugurel-Bricolaj` / `bricolaj1234!`.

### Doua modificari in fisiere care ajung pe server

Ambele sunt conditionate ca sa **nu** schimbe nimic in productie, dar e bine sa stii ca exista:

- `Website/.htaccess` — bloc `<IfModule php_module>` care neutralizeaza local handlerul cPanel
  `ea-php81`. Fara el, Apache local serveste fisierele `.php` ca text. Pe cPanel (LSAPI)
  `php_module` nu exista, deci blocul e ignorat.
- `Website/admin/.htaccess` — exceptii de la fortarea HTTPS pentru `localhost`, `127.0.0.1` si
  `.test`. Verificat cu `Host: mugurel-bricolaj.ro` ca productia primeste tot 301 spre HTTPS.

### Ramase deschise / de verificat

- [ ] **Posibil bug in catalogul actual, neconfirmat.** `catalog.html?cat=constructii` a afisat
      toate cele 84 de produse cu chip-ul „Toate" activ, desi titlul si breadcrumb-ul se
      schimbasera corect in „Constructii" — deci scriptul rulase. Ar fi trebuit sa arate 10.
      Nu a fost diagnosticat; poate fi si cache de browser. **Task 8 rescrie complet zona asta**
      (`catalog.php` din DB, `SUBCAT_PARENT`/`CAT_LABELS` eliminate, contor server-side), deci
      de verificat explicit acolo, nu de reparat separat.
- [ ] **Slug-uri divergente intre chipsuri si DB**, de aliniat in Task 8: chip `scule` vs DB
      `scule-unelte`, `gard` vs `gard-imprejmuiri`, `zidarie` vs `zidarie-bca`. Netratate,
      filtrele astea ar returna zero produse — exact bug-ul de la care am plecat, mutat in alt loc.
- [ ] **PHP 8.1 vs 8.3.** Serverul ruleaza 8.1, local avem 8.3. Codul e format din functii
      simple si array-uri, iar reviewerii nu au gasit sintaxa post-8.1, dar nu s-a putut
      verifica prin executie. De rulat `php -l` pe server dupa primul deploy.
- [ ] `test_db.php` verifica doar numaratori (31/9/22/0), nu si ca fiecare subcategorie are
      parintele corect. Ar trece si daca toate ar fi atasate gresit la acelasi parinte.
- [ ] Schema nu impiedica structural un al treilea nivel de categorii sau un ciclu. Relevant
      cand adminul va putea crea categorii (Task 9).

### Reguli reconfirmate in aceasta sesiune

- **Fara `Co-Authored-By: Claude`** sau orice mentiune Claude/Anthropic, nicaieri.
- **Fara `git push` fara acord explicit**, de fiecare data.
- Toate modificarile se vad **intai local**, inainte de orice deploy.
- Branch-ul `feat/catalog-db` nu are inca upstream — primul push va cere `-u origin feat/catalog-db`.


---

## 20 august 2026 — Microsoft Clarity + reparare catalog

**Repo:** `Website/` → [capitalboost/mugurel-website](https://github.com/capitalboost/mugurel-website), branch `main`
**Site live:** https://mugurel-bricolaj.ro
**Commits:** `2503257` → `41edc4b` (6 commits, toate împinse)

### 1. Microsoft Clarity instalat (ID `y5btegvf2j`)

Tag-ul stă în `<head>`, imediat sub blocul Google Analytics (GA4: `G-R6M7YYLB48`), pe:
- toate cele 20 de pagini `.html` din rădăcină
- `finantari-europene/index.html`
- `templates/header.php` — head-ul comun pentru paginile dinamice servite prin `router.php`
  (categorii din DB + pagini statice din admin), deci acoperă automat tot ce se generează de acolo

Panoul `/admin` e **intenționat** netrackuit — nu are rost să înregistrezi sesiuni de administrare.

**Capcana care ne-a costat timp:** tag-ul era corect pus, dar nu trimitea date, pentru că
antetul `Content-Security-Policy` din `.htaccess` (linia 49) permitea la `script-src` doar
googletagmanager și google-analytics. Browserul bloca `clarity.ms`. Rezolvat în `f52d9fa`:
adăugate `https://www.clarity.ms https://*.clarity.ms` la `script-src` și
`https://*.clarity.ms https://c.bing.com` la `connect-src`.

> **De reținut:** orice tracker/script terț nou adăugat pe site trebuie trecut și în CSP-ul
> din `.htaccess`, altfel e blocat silențios de browser. Simptom tipic: tag-ul e în HTML,
> dar dashboard-ul rămâne gol.

### 2. Politica de confidențialitate actualizată

- secțiune nouă **„6. Cookie-uri si instrumente de analiza"** — GA4 și Clarity numiți explicit,
  ce colectează fiecare (inclusiv heatmaps și înregistrări de sesiune, cu mascarea automată a
  câmpurilor de formular), transfer în afara SEE pe clauze contractuale standard, opțiuni de opt-out
- completări la secțiunile 2 (date tehnice și de utilizare), 3 (scop de analiză), 4 (temei: consimțământ)
- renumerotare secțiuni 6-8 → 7-9, dată actualizată
- corectată adresa ANSPDCP: `anspdcp.eu.int` (domeniu mort) → `www.dataprotection.ro`

### 3. Banner cookie-uri — text nou

În `js/main.js`, orientat pe beneficiu, fără să numească tool-urile:
> „Folosim cookie-uri pentru functionarea corecta a site-ului si pentru a intelege cum este folosit.
> **Daca accepti, ne ajuti sa imbunatatim site-ul** si sa iti oferim o experienta mai buna."

**Decizie luată conștient:** butoanele Accept/Refuz **nu blochează** nimic — GA4 și Clarity se
încarcă pentru toată lumea. S-a ales varianta asta ca să nu se piardă date de la vizitatorii
care refuză sau pleacă înainte să apese. Textul politicii a fost formulat să descrie situația
reală (acord prin continuarea navigării), nu să promită o blocare tehnică inexistentă.
**Rămâne deschis:** dacă se dorește vreodată conformitate GDPR strictă, e o modificare de ~30 de linii
(trackerele se încarcă doar după Accept).

### 4. Bug reparat: subcategoriile din sidebar duceau la catalog gol

**Simptom:** click pe „Incalzire → Centrale termice" în meniul din stânga → „Nu am gasit produse",
dar același „Incalzire" din meniul de sus funcționa.

**Cauza:** sidebar-ul linkuiește slug-uri de nivel 2 (`catalog.html?cat=centrale`), dar produsele
erau etichetate doar cu categoria părinte în `data-cat`. Filtrul căuta o etichetă inexistentă →
ascundea tot. Afecta **17 sub-item-uri**, nu doar centrale. Cele de la Construcții mergeau doar
pentru că au pagini proprii (`acoperis.html` etc.), nu trec prin filtru.

**Rezolvare, în două etape:**
1. `2302683` — tabel `SUBCAT_PARENT` în `js/main.js`: subcategoria cade pe categoria părinte
2. `31a6ed3` — filtrare reală: produsele au primit `data-subcat`, filtrul face potrivire exactă
   când subcategoria are produse; când nu are, afișează categoria părinte **plus o notă galbenă
   explicită** (`.cat-fallback-note`) care spune că nu avem produse listate la subcategoria aia
   și trimite pe WhatsApp

Etichetele de categorii au fost mutate în `CAT_LABELS` (`js/main.js`), folosit și de `catalog.html`
pentru titlu și breadcrumb.

### 5. Catalog extins: 44 → 84 de produse

Cele 6 subcategorii care rămăseseră goale au primit câte 5 produse, apoi Mobilier încă 10:

| Subcategorie | Adăugate | Total acum |
|---|---|---|
| Corpuri Iluminat | 5 | 5 |
| Aer condiționat | 5 | 5 |
| Sobe & Șeminee | 5 | 5 |
| Mobilier baie | 5 | 5 |
| Accesorii baie | 5 | 5 |
| Bucătărie | 5 + 2 | 7 |
| Living | 4 | 5 |
| Dormitor | 4 | 6 |

Specificațiile sunt realiste pentru piața din RO (research pe gama Dedeman/Hornbach pentru
dimensiuni și finisaje uzuale), dar **produsele sunt plauzibile, nu preluate din stocul real** —
descrieri generice de tip produs, fără marcă, preț „la cerere", buton WhatsApp pe fiecare.
De înlocuit când există date reale de stoc.

**Produse în categorii multiple:** `data-cat` acceptă acum mai multe valori separate prin spațiu.
Țeava PPR apare și la Încălzire, și la Apa & Canal → Țevi & Fitting (`data-cat="incalzire apa-canal"`).
La fel chiuveta de bucătărie (`mobilier sanitare`). Badge-ul vizual rămâne unul singur.

Reparat pe drum: contorul „Afisare: N produse" era o valoare fixă în HTML, rămasă în urmă;
acum se calculează la încărcare.

---

## Cum se ajunge modificările pe site (important, nu e automat)

1. `git push origin main` → codul ajunge doar pe GitHub, **nu pe site**
2. cPanel → **Git Version Control → Deploy HEAD Commit** → rulează `.cpanel.yml`,
   care copiază fișierele în `/home/mugurel/public_html/`
3. Pentru `js/` și `css/`: **Cloudflare le cache-uiește 4 ore** (`cf-cache-status: HIT`).
   Paginile `.html` nu sunt cache-uite (`DYNAMIC`), se văd imediat.
   Ca să apară instant după deploy: Cloudflare → Caching → **Purge Everything**, apoi Ctrl+Shift+R.

Verificare rapidă că un fișier a ajuns live, ocolind cache-ul:
`curl -s "https://mugurel-bricolaj.ro/js/main.js?v=123" | grep <ceva-din-fisier>`

---

## Rămase deschise (din sesiunea 20 august)

> Confirmate ca rezolvate pe 27 august: deploy-ul a fost facut, iar Clarity a fost verificat.
> Raman deschise doar ultimele trei, care nu erau blocante.

- [x] **Deploy final** — commit-urile `0628917` și `41edc4b` au ajuns pe site
- [x] **Verificat Clarity** — datele apar în dashboard în 30 min – 2h de la prima vizită reală.
      Dacă rămâne gol: F12 → Network, filtru `clarity`, ar trebui status 200 pe
      `clarity.ms/tag/y5btegvf2j`. Atenție la adblock, care blochează clarity.ms din listele standard.
- [ ] **Produsele demo** — de înlocuit cu stoc real când există
- [ ] **Consimțământ real pentru cookie-uri** — opțional, decizie amânată conștient (vezi punctul 3)
- [ ] Descrierile produselor noi încep direct cu specificațiile, în timp ce cele originale reiau
      întâi tipul produsului („Centrala pe gaz cu condensare, 24kW..."). Diferență acceptată explicit,
      nu e o scăpare.

---

## Convenții de respectat

- **Fără `Co-Authored-By: Claude`** în commit-uri, PR-uri, nicăieri. Valabil pe orice repo.
- Textele de pe site sunt **fără diacritice** (convenția existentă în `catalog.html` și restul paginilor).
- Descrierile de produs: ~85 de caractere, 2-3 propoziții scurte, preț „la cerere".
