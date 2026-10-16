# Plan — catalogul nou (fisierele Dunlite) + lansarea cosului

**Stare:** propus 16 oct 2026, **neinceput**. Se executa pas cu pas, cu oprire la punctele marcate 🛑.
**Branch:** `feat/catalog-nou`, pornit din `feat/cos-comenzi` (are deja pret, stoc, cos, comenzi).
**Sursa:** `Fisiere de lucru/Prouse Dunlite client/constructii_complet_MU.xlsx` (147 produse). Urmeaza inca 2 fisiere:
alte materiale de constructii + electrocasnice, **in acelasi format** (de confirmat cand vin).

---

## Decizii (16 oct, cu Lucian)

| Subiect | Decizie |
|---|---|
| Catalog vechi | Cele 534 de produse si cele 31 de categorii se **sterg**. Cele 13 pagini vechi → **redirect 301** catre categoria noua cea mai apropiata; cele fara echivalent → `/catalog.html`. |
| Categorii | Din coloana `Categorie` (`Ramura > Grupa > Subcategorie`), **create automat la import**. „Metal si profile” ramane **sub** Materiale constructii, ca in fisier. Grupele cu o singura subcategorie se afiseaza fara nivelul gol. |
| Pagina produsului | **Pagina proprie pentru fiecare produs** (`/produs/<slug>.html`): descriere completa, tabel de specificatii, fisa tehnica PDF, pret, stoc, cos. Cardul din categorie ramane scurt. |
| Variante | **Fiecare cod de bare = un produs/card**. Navigarea prin **filtre** (dimensiune, grosime etc.). |
| Tag-uri | **Atributele** (brand, grosime, dimensiune, lungime, culoare, interior/exterior) → **filtre**. **Utilizarile** (baie, bucatarie, fatada, fier forjat, imprejmuiri…) → **pagini-colectie**. Tag-ul „constructii” (pe toate) se ignora. Se repara tag-urile rupte de virgula zecimala („grosime 1” + „5 mm” → „grosime 1,5 mm”). |
| Ce se publica | **Toate** produsele, indiferent de `Status`. Fara pret → „Pret la cerere” + WhatsApp, fara buton de cos. „De verificat”/„Negasit” → semn vizibil **doar in admin**. |
| Poze | Panou generat (ca acum); Mugurel incarca poze reale din admin. |
| Coloane interne | `Sursa (URL)` si `Observatii` **nu apar pe site** (raman in baza, vizibile in admin). `Documente` → link „Fisa tehnica”. |
| Lansare | **Imediat cu primul fisier, catalog nou + cos impreuna.** Deci textele de plata/livrare (A3) si paginile legale (A6) trebuie gata **inainte**. |

---

## Pasi

| # | Ce | Rezultat verificabil |
|---|---|---|
| **N1** | **Structura bazei** (migrari 011+): categorii pe 3 niveluri cu `slug` si cale; la produse `barcode` (UNIC — cheia pentru importurile viitoare), `sku`, `original_name`, `manufacturer`, `product_type`, `slug`, `description_html`, `verify_status`, `internal_notes`, `source_urls`; tabele noi `product_specs` (cheie/valoare, ordonate), `product_documents`, `tags` + `product_tags` (tip: atribut / utilizare). | Migrari idempotente, rulate de 2 ori fara erori; test pe baza goala + pe copia live `mugurel_live_20261016`. |
| **N2** | **Importul din Excel** (`db/import_catalog.php`): citeste `.xlsx` direct; actualizeaza dupa codul de bare (reimportul nu dubleaza); pret „25,24” → 25.24; specificatii „Cheie: valoare”; tag-uri reparate; HTML-ul descrierii **curatat pe lista alba** (p, strong, ul, li…). Fara `--apply` doar raporteaza. | Raport: 147 produse, 18 subcategorii, 0 erori; lista tag-urilor reparate; produsele fara pret. |
| 🛑 | **Verifici raportul de import** (categorii, tag-uri → filtre / colectii). | |
| **N3** | **Pagini de categorie generice** (un singur sablon pentru orice categorie din baza): breadcrumb, sub-categorii, grila de carduri, paginare, filtre, JSON-LD. Inlocuieste cele 13 fisiere scrise de mana. | Toate categoriile 200; filtrele reduc corect lista; paginile filtrate `noindex`. |
| **N4** | **Pagina produsului**: descriere, specificatii, fisa tehnica, pret, stoc, „Adauga in cos”, JSON-LD `Product` cu **GTIN** (codul de bare — ajuta in Google Shopping). | 147 pagini 200; validare JSON-LD. |
| **N5** | **Colectii** din tag-urile de utilizare (ex. „Fier forjat si imprejmuiri”, „Pentru fatada”). | Fiecare colectie listeaza produsele corecte. |
| **N6** | **Navigare**: meniul din header generat din baza, footer, prima pagina (produse recomandate noi, alt `data-product-id`), `catalog.html`, **cautare simpla** (nume, brand, cod de bare). | Niciun link spre pagini vechi; testele de navigare la 3 latimi. |
| 🛑 | **Te uiti pe localhost** la categorii, filtre, pagina de produs. | |
| **N7** | **Admin**: campurile noi, specificatii, tag-uri, documente, semn „De verificat”, poza; **import Excel din admin** (pentru urmatoarele 2 fisiere, fara mine). | Adaug/editez un produs din admin si apare corect; import din admin = import din linia de comanda. |
| **N8** | **SEO si stergerea catalogului vechi**: redirect 301 pentru cele 13 pagini vechi, `sitemap.xml` generat din baza, canonical. **Garda din `deploy_db.php` schimbata** si `productie_date.sql` retras — altfel deploy-ul reimporta cele 534 de produse vechi. Stergerea catalogului vechi = pas explicit, cu backup, nu automat. | Test pe copia live: deploy → catalog nou, nimic vechi reimportat; redeploy idempotent. |
| **N9** | **Cosul pe catalogul nou**: ID-urile noi, fixturile si testele refacute; produsele fara pret nu intra in cos. | Cele 516 asertiuni PHP + testele din browser trec pe datele noi. |
| **N10** | **Texte pentru lansare** (conditiile A3 + A6): propuneri pentru plata/livrare/emailuri si pentru Termeni (comanda online, retur 14 zile cu exceptiile, ANPC SAL/SOL) + Politica de confidentialitate (datele din comanda). *Nu inlocuiesc un jurist.* | 🛑 **Aprobi textele.** |
| **N11** | **Test integrat** (fluxuri complete la 3 latimi, emailuri in Mailpit, accesibilitate) + **plan de lansare actualizat** (`PLAN-LANSARE-COS.md`). | 🛑 **Raport final → aprobarea ta pentru productie.** |

Lansarea propriu-zisa (deploy, `config.local.php` cu SMTP, comanda de proba) se face **doar dupa aprobarea explicita**,
dupa `PLAN-LANSARE-COS.md` actualizat.

## Ramase deschise
- **Stocul real** (A2): implicit „In stoc” la toate — ramane asa la lansare?
- Formatul celor 2 fisiere urmatoare — se verifica la sosire; importul accepta orice ramura noua (ex. Electrocasnice).
- `Status` „De verificat” la 117 produse: descrierile/specificatiile pot avea erori pana sunt verificate.

---

## Progres

### N1 — structura bazei: GATA (16 oct)
`db/migration_011_catalog_nou.sql`, adaugata in `deploy_db.php`. Testata: baza goala (`dev/test_fresh_deploy.sh`, 2 rulari)
si copia bazei de productie `mugurel_live_20261016` (migrarile 002–011 prin `deploy_db.php`, 2 rulari) — 0 erori.

### N2 — importul din Excel: GATA (16 oct), asteapta verificarea raportului 🛑
- `db/import_catalog.php` (+ `db/catalog_rules.php` cu regulile tag-urilor, `includes/catalog_text.php` cu slug-uri,
  HTML pe lista alba si rezumatul pentru card). `readXlsxSheet()` citeste acum si „prima foaie".
- Teste: `admin/tests/test_import_catalog.php` — 71 asertiuni (format, tag-uri reparate, clasificare, HTML periculos,
  reimport fara dubluri, poza/stocul pastrate la reimport, al doilea fisier nu atinge primul, rollback la eroare,
  fisierul real: 147 produse / 0 erori / 17 tag-uri reparate).
- **Baza LOCALA are acum catalogul nou** (147 produse, 26 categorii, 145 tag-uri; cele 534 vechi sterse — copie in
  `Backup/2026-10-16/baza-locala-mugurel_cms.sql`). Reimportul aceluiasi fisier: 0 noi, 147 actualizate.
- **Asteptat, se repara in N3/N9:** testele legate de catalogul vechi pica (`test_products_repo`, `test_db`, `test_cart`,
  `test_orders`, `test_order_mail`, `test_order_security` — folosesc ID-uri si pagini vechi). Paginile vechi de
  categorie sunt goale local pana la N3.

### N3–N6 — liste, pagina produsului, colectii, navigare: GATA (16 oct), asteapta verificarea pe localhost 🛑
- **Colectii** (`db/migration_012_collections.sql`): 5 colectii legate de tag-uri prin slug — Fier forjat 14,
  Termoizolatii 12, Fatada 11, Masa de spaclu 11, Baie si bucatarie 4. Sub 3 produse o colectie nu se afiseaza.
- **Liste** (`includes/catalog_repo.php`, `includes/catalog_page.php`, `templates/catalog_list.php`, `js/catalog-filtre.js`):
  categorie / colectie / cautare cu acelasi sablon — arbore de categorii, filtre din tag-uri (SAU in grupa, SI intre grupe;
  o grupa apare doar cu 2+ valori), ordonare, paginare 30/pagina. Paginile filtrate/sortate: `noindex, follow`, canonical
  spre categorie. Categoriile „de trecere" (ex. Ciment si var > Ciment) sar un nivel (302 + lipsesc din meniu/breadcrumb).
- **Pagina produsului** (`produs.php`, `/produs/<slug>.html` in `.htaccess` + router local): descriere (HTML curatat si la
  afisare), specificatii, fisa tehnica, pret, stoc, cos, WhatsApp, produse similare, JSON-LD `Product` (GTIN doar cu cifra de
  control valida — 83 de coduri interne „100…" nu se trimit) + `BreadcrumbList`.
- **Navigare**: meniul de sus, meniul de pe telefon, footer-ul si cautarea (formular real, `/catalog.html?q=`) generate din
  catalog (`includes/site_nav.php`). `catalog.php` rescris (toate produsele + cautare; `?cat=` vechi => 301).
- **Paginile statice trecute pe sabloane**: `index`, `contact`, `despre-noi`, `politica-*`, `termeni-conditii` sunt acum `.php`
  (continut identic; `.cpanel.yml` sterge automat `.html`-ul vechi de pe server cand exista `.php`). Prima pagina:
  sidebar, grila de categorii si 6 produse recomandate din catalog (`includes/home.php`); `js/produse-acasa.js` scos.
  Bonus: rezolva punctul 9 din jurnal (versiune la CSS/JS pe toate paginile).
- Teste: `admin/tests/test_catalog_repo.php` — 38 asertiuni (categorii, filtre, cautare cu `%`, sortare, colectii, produs,
  pagini HTTP: coduri, robots/canonical, JSON-LD, 404, 301/302, XSS in cautare). Capturi 375/1280 px: fara scroll orizontal,
  fara erori JS.
- **Ramane pentru N8:** cele 13 pagini vechi (`acoperis.php`…) inca exista — devin redirect 301.
- **Texte de revazut (N10):** prima pagina inca spune „Constructii · Mobilier · Electrocasnice", „3000+ produse", „instalatii,
  mobilier"; despre-noi/contact mentioneaza categoriile vechi in text.

### Al doilea fisier — `tabla_complet_MU.xlsx`: IMPORTAT local (16 oct)
164 produse: Materiale constructii > **Acoperis** (149: accesorii, sisteme pluviale, folii, tabla tip tigla/cutata/plana),
ramuri noi **Incalzire** (14, burlane pentru sobe) si **Instalatii electrice** (1). Total local: **311 produse**.
Reguli noi (`includes/catalog_rules.php`): zecimalele se compara ca valoare („0,40 mm" in denumire = tag „grosime 0,4 mm" —
68 de grosimi reparate), grupe noi **Finisaj** (mat/lucios/emailat) si **Lungime (module)**, culori compuse si coduri RR,
dimensiuni cu „×", g/m² si m²; un tag identic cu numele categoriei („acoperis") se ignora.
**N8 partial:** cele 13 pagini vechi `.php` sterse; `/acoperis.html` este acum categoria noua (aceeasi adresa!), celelalte
au redirect 301 (`router.php`, `LEGACY_REDIRECTS`). Meniul pastreaza numele ramurilor („Incalzire"), adresa duce direct
la singura subcategorie.

### N7 — admin: GATA (16 oct)
- `admin/models/CatalogProduct.php`, `admin/controllers/ProductsController.php` (rescris), `admin/views/products/{list,edit}.php`:
  lista cu cautare (nume, cod de bare, SKU, brand) si filtre (categorie cu subcategorii, verificare, stoc, poza, pret,
  vizibilitate), 50/pagina, ordonata dupa arborele de categorii; formular cu toate campurile noi — pret cu virgula,
  descriere HTML (curatata pe lista alba la salvare), specificatii pe randuri, tag-uri separate prin „;" (virgula ramane
  pentru zecimale), fise tehnice, note interne, sursele si denumirea din gestiune afisate doar in admin. Cod de bare gol la
  produs nou => cod intern `INT-…`. Adresa produsului nu se schimba la redenumire. Validare cu formular pastrat la eroare.
- **Import Excel din admin** (`admin/controllers/ImportController.php`, `admin/views/import.php`): incarci .xlsx (max 10 MB,
  verificat ca arhiva Excel) => raport + cifre (nimic scris) => „Aplica importul" (token legat de sesiune, fisierul temporar
  se sterge, intrare in `audit_log`). Acelasi cod ca linia de comanda — importul s-a mutat in `includes/catalog_import.php`
  (+ `includes/xlsx_reader.php`), pentru ca `db/` nu ajunge pe server.
- Dashboard: „Fara poza", „De verificat", „Fara pret", cu legaturi spre lista filtrata.
- Teste: `dev/e2e/tests/admin-catalog.spec.js` (5 teste: acces fara login, cautare/filtre, produs nou cu validare + pagina
  pe site + colectie dupa tag + stergere, stoc/vizibilitate pe site, import gresit respins + import real fara dubluri).
  `admin-stoc.spec.js` (catalogul vechi) sters.
