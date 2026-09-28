# Catalog unificat pe baza de date — design

**Data:** 27 august 2026
**Repo afectat:** `Website/` (mugurel-bricolaj.ro), inclusiv `Website/db/` (scripturile de bootstrap ale bazei de date)
**Status:** aprobat, urmeaza planul de implementare

---

## Problema

Site-ul are trei liste de produse care nu comunica intre ele:

| Sursa | Produse | Cine o vede |
|---|---|---|
| `catalog.html` | 84, hardcodate in HTML | vizitatorii pe /catalog.html |
| Paginile de categorie (13 fisiere `.html`) | 231, hardcodate separat | vizitatorii pe fiecare categorie |
| Baza de date + `/admin` | ce importa `migrate_products.php` | nimeni |

Simptomul raportat — "catalogul arata doar 84 de produse" — nu e un bug de filtrare. `catalog.html`
contine exact 84 de carduri scrise de mana si nu atinge baza de date. Contorul e corect; continutul
e incomplet. Filtrul "Electrice" arata 11 produse in catalog, dar `electrice.html` are 23.

Sub el sta o problema mai grava: **panoul `/admin` nu are niciun efect asupra site-ului.** In
`.htaccess`, regula de passthrough pentru fisiere reale (`RewriteCond %{REQUEST_FILENAME} -f`)
ruleaza inaintea rewrite-ului catre `router.php`, iar `.cpanel.yml` copiaza `*.html` in
`public_html/`. Deci `electrice.html` exista fizic, Apache il serveste static, si `router.php`
nu e apelat niciodata pentru categorii. Tot lantul CMS construit in iunie (schema, CRUD, migrare,
router, templates) e complet deconectat de la vizitator.

## De ce fixul evident e gresit

Reflexul ar fi sa mutam regula de rewrite si sa lasam `router.php` sa serveasca toate categoriile
din DB. Asta ar distruge SEO-ul.

`electrice.html` are ~1400 de linii: JSON-LD schema.org, H1 optimizat local, sectiuni editoriale
("De ce conteaza o instalatie electrica corecta?"), 5 subsectiuni tematice cu ancore, FAQ.
`templates/category.php` are 55 de linii: H1 plus grila de produse. Activarea router-ului ar
inlocui pagini bogate cu pagini aproape goale, pe un site care traieste din cautari locale.

Concluzia care determina tot designul: **baza de date devine sursa de adevar doar pentru produse.
Invelisul editorial ramane in fisiere.**

## Ce nu suporta schema actuala

Schema a fost scrisa in iunie; functionalitatile de mai jos au aparut in august. Migrarea produselor
in DB fara extinderea schemei ar pierde toate patru:

1. **Subcategorii.** `categories` nu are `parent_id`. Frontendul are 17 subcategorii in sidebar
   (`SUBCAT_PARENT` in `js/main.js`).
2. **Produse in categorii multiple.** `products.category_id` e un singur FK NOT NULL. Frontendul
   accepta `data-cat="incalzire apa-canal"` (teava PPR, chiuveta de bucatarie).
3. **Categoria `constructii` lipseste complet din DB.** In frontend e categorie de nivel 1 cu 5
   copii (acoperis, izolatie, gips-carton, zidarie-bca, gard-imprejmuiri). In seed-ul din
   `schema.sql` cele 5 sunt categorii plate de nivel 1, iar `constructii` nu exista ca rand.
4. **Slug inconsistent.** `js/main.js` foloseste `scule`, `schema.sql` foloseste `scule-unelte`.

In plus, numele din seed au diacritice ("Acoperis" cu s-cedila, "Incalzire" cu I-breve), dar
conventia site-ului e text fara diacritice. Randate in badge-uri, ar rupe conventia vizuala.

## Solutia

### Schema — cinci modificari

**`categories.parent_id`** — SMALLINT UNSIGNED NULL, FK self-referential, maxim doua niveluri.
Cele 17 subcategorii devin randuri reale. Se adauga randul `constructii` ca parinte al celor 5
categorii de materiale de constructii. Slug-ul `scule-unelte` ramane canonic; `js/main.js` se
alinieaza la el. Numele se rescriu fara diacritice, conform conventiei site-ului.

**`products.category_id` capata un singur inteles: categoria cea mai specifica** — subcategoria
daca produsul are una, altfel categoria de nivel 1. Apartenenta la nivel 1 se deriva urcand prin
`parent_id`. Coloana nu isi schimba tipul si nu se adauga o a doua coloana de categorie, deci nu
exista doua locuri care spun acelasi lucru.

**`product_categories(product_id, category_id)`** — tabel de legatura folosit *exclusiv* pentru
apartenente suplimentare (cross-listing). Cele doua produse afectate azi intra aici. Badge-ul
vizual ramane unul singur, cel derivat din `category_id`.

`SUBCAT_PARENT` si `CAT_LABELS` din `js/main.js` devin redundante — ierarhia si etichetele traiesc
in DB. Se elimina la pasul 4, nu mai devreme, ca `catalog.html` sa continue sa functioneze in timpul
tranzitiei.

### Doua componente noi

**`includes/products_repo.php`** — doar SQL, fara HTML. Expune `productsForSection($pageSlug, $sectionKey)`,
`productsForPage($pageSlug)` si `allProducts()`. Toate accepta `limit` si `offset` optionale,
nefolosite acum, prezente pentru cand apar cele 3000 de produse.

**`includes/product_grid.php`** — doar randare, fara SQL. Expune **doua** functii peste aceleasi
date, fiindca site-ul are doua carduri vizual distincte, fara clase comune:

| | Paginile de categorie | `catalog.html` |
|---|---|---|
| Container | `.material-card` | `.prod-card` |
| Titlu | `.material-name` | `.prod-name` |
| Descriere | `.material-props` / `.prop-label` / `.prop-val` | `.prod-desc` |
| Buton WhatsApp | `<a class="btn-wa-material" href="wa.me/...">` | `<button class="btn-wa-prod" data-wa-product>` |
| Atribute filtru | — | `data-cat`, `data-subcat` |

`renderMaterialCards()` si `renderProdCards()`. Ambele variante raman exact cum arata azi;
unificarea lor vizuala ar fi o schimbare de design care nu face parte din aceasta lucrare. CSS-ul
si filtrele JS existente raman neatinse.

**`includes/product_icons.php`** — biblioteca de iconite SVG. Fiecare card are azi o iconita
desenata manual: **66 distincte**, folosite de 227 dintre cele 231 de carduri (17 iconite
acopera 176 de carduri, restul de 49 apar o singura data). Cele 4 carduri fara iconita sunt in
`apa-canal.html` si folosesc alt markup. `products` primeste o coloana **`icon_key VARCHAR(40)`**, iar migrarea potriveste
automat SVG-ul existent cu cheia lui, deci aspectul ramane identic 1:1. Fisierul se **genereaza cu
un script** din HTML-urile existente, nu se transcrie manual — 66 de SVG-uri copiate de mana ar
introduce garantat erori. In admin, iconita se alege dintr-un select la produs nou; produsele fara
`icon_key` primesc o iconita generica.

### Routing — URL-urile publice raman `.html`

In `.htaccess`, o regula noua **inaintea** celei de passthrough:

```apache
RewriteCond %{DOCUMENT_ROOT}/$1.php -f
RewriteRule ^([a-z0-9][a-z0-9-]*)\.html$ /$1.php [L,QSA]
```

`/electrice.html` serveste `electrice.php`. Zero redirect-uri, zero URL schimbat, zero impact SEO.
Paginile pur statice (`contact.html`, `despre-noi.html`, `politica-*.html`) nu sunt atinse, fiindca
nu au `.php` corespondent. `router.php` ramane pentru paginile care exista doar in DB.

### Plasarea editoriala: `page_slug` + `section_key`

Produsele **nu stau intr-un singur bloc per pagina**. Sunt imprastiate in **54 de sectiuni
editoriale pe cele 13 pagini** — `electrice.html` are 23 de produse in 4 sectiuni, din care
"Produse Disponibile in Magazin" contine doar 6. Gruparile editoriale nu corespund subcategoriilor
din sidebar: aceeasi pagina are si "Cabluri & Conductori" (subcategorie), si "Cabluri si Conductori
Electrici" (sectiune editoriala). Verificat: **zero produse duplicate** — sectiunile cu titluri
similare contin produse diferite.

Taxonomia si plasarea editoriala sunt doua preocupari distincte si primesc coloane distincte:

| Preocupare | Coloane | Determina |
|---|---|---|
| Taxonomie | `category_id`, `product_categories` | badge-ul, filtrele din catalog |
| Plasare editoriala | `page_slug`, `section_key` | in ce pagina si in ce sectiune se randeaza |

**`products.page_slug VARCHAR(80) NOT NULL`** — pagina de categorie in care apare produsul (una din
cele 13). **`products.section_key VARCHAR(60) NULL`** — sectiunea din acea pagina, ca slug derivat
automat din titlul ei (`cabluri-conductori`, `produse-disponibile-in-magazin`). Migrarea deduce
cheia din `<h2>`, deci nu se mapeaza nimic manual.

### Ce se schimba in paginile de categorie

`electrice.html` devine `electrice.php`. JSON-LD, H1, textul editorial, FAQ-ul si **toate headerele
de sectiune** (`<h2>` plus descrierea — continut indexat) raman neatinse in fisier. Se inlocuieste
doar continutul fiecarui `<div class="materials-grid">` cu un apel filtrat pe sectiune:

```php
<?= renderMaterialCards(productsForSection('electrice', 'cabluri-conductori')) ?>
```

Pagina ramane identica vizual. Cele 54 de sectiuni devin 54 de apeluri, iar adminul ajunge sa
controleze toate cele 231 de produse, nu doar cele 44 din blocurile finale.

`.cpanel.yml` copiaza deja `*.php`. Fisierele `.html` vechi ramase in `public_html/` devin inerte,
fiindca noua regula de rewrite ruleaza inaintea passthrough-ului; se sterg totusi la deploy, ca sa
nu ramana continut mort pe server.

### Error handling

**Daca baza de date pica, pagina se randeaza oricum.** Tot continutul editorial ramane, iar in locul
grilei apare un mesaj discret plus butonul WhatsApp. Niciodata 500. Pierderea paginilor din index
din cauza unui hiccup MySQL ar fi un esec tacut, greu de observat luni de zile.

Daca o categorie nu are produse vizibile: acelasi mesaj plus WhatsApp, ca acum.

## Secventa

Pilot pe o singura categorie, validat live, apoi replicare.

| Pas | Continut | Verificare |
|---|---|---|
| 1 | Migrare schema: `parent_id`, `product_categories`, seed subcategorii, rand `constructii`, nume fara diacritice | Ierarhia se citeste corect; fiecare subcategorie are parinte valid |
| 2 | Generare `product_icons.php` + rescriere `migrate_products.php` — importa cele 231 de produse cu subcategoria si `icon_key` corecte, idempotent (rerulabil fara dubluri) | Numar produse pe categorie in DB = numar carduri in fisierul HTML corespunzator |
| 3 | **Pilot Electrice:** `electrice.php` (4 sectiuni) + `products_repo.php` + `product_grid.php` + `product_icons.php` + regula `.htaccess` | vezi mai jos |
| 4 | Restul de 12 categorii + `catalog.php`; eliminare `SUBCAT_PARENT`/`CAT_LABELS` din `js/main.js` | Contor corect, filtre functionale, o singura sursa de adevar |

**Criterii de acceptanta pentru pilot:**

- `curl` pe `/electrice.html` intoarce 200 si contine JSON-LD, H1 si FAQ-ul
- diff intre HTML-ul vechi si cel nou randat arata diferente **doar** in blocul de produse
- numarul de carduri randate = 23, distribuite 4/4/9/6 pe cele patru sectiuni, ca azi
- iconitele randate sunt identice cu cele de azi, card cu card
- ancorele `#cabluri`, `#tablouri`, `#prize`, `#iluminat`, `#doze` functioneaza
- cu DB-ul oprit, pagina se randeaza in continuare cu tot continutul editorial

Replicarea pe restul categoriilor incepe abia dupa ce toate cinci trec.

## In afara scope-ului

- Unificarea vizuala a celor doua carduri (`.material-card` vs `.prod-card`). Raman distincte.
- Importul celor 3000+ de produse. Nu exista in forma digitala; stocul e in programul de gestiune
  sau pe hartie. Infrastructura se construieste acum, importul cand apar datele.
- Editarea continutului editorial din admin. Decizie explicita: adminul gestioneaza **doar produsele**.
- Paginare si cautare in interfata. Repo-ul le suporta, interfata nu le expune inca.
- Consimtamant real pentru cookie-uri (decizie amanata anterior, nelegata de aceasta lucrare).

## Riscuri

**Pierdere SEO la conversie.** Mitigat prin pastrarea URL-urilor si prin criteriul de diff care
cere ca singura diferenta sa fie blocul de produse.

**Divergenta intre DB si fisierele HTML in timpul tranzitiei.** In pasii 3-4 coexista categorii
convertite si neconvertite. `catalog.html` ramane pe datele lui hardcodate pana la pasul 4, cand
trece pe DB odata cu ultimele categorii.

**Deploy-ul nu e automat.** `git push` duce codul doar pe GitHub. Ajunge pe site abia dupa
cPanel -> Git Version Control -> Deploy HEAD Commit. Fisierele din `js/` si `css/` sunt cache-uite
4 ore de Cloudflare si cer Purge Everything.
