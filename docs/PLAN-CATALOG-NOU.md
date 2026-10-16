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
